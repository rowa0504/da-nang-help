# Terraform — Da Nang Help AWS基盤

このディレクトリはAWSインフラをTerraformで管理するためのコードです。詳細な方針・コスト試算・state管理設計は[docs/RELEASE_PLAN.md](../../docs/RELEASE_PLAN.md)の4節を参照してください（本READMEは実行手順に専念します）。

## 現在の実装範囲（第1〜3B段階）

- `modules/network/`: VPC、public subnet×2、private subnet×2、Internet Gateway、ルートテーブル（NAT Gatewayなし）
- `modules/ecr/`: コンテナイメージ用ECRリポジトリ（IMMUTABLE・scan_on_push・lifecycle policy）
- `modules/s3/`: Service Request写真用S3バケット（TLS強制・BucketOwnerEnforced・SSE-S3）
- `modules/logging/`: コンテナ（nginx/php-fpm/worker/scheduler）ごとのCloudWatch Log Group
- `modules/iam/`: ECS Task Execution Role・Task Role
- `modules/security/`: ALB/ECS Task/RDS用Security Group、RDS DB Subnet Group（詳細は後述の「第3段階の分割（3A〜3D）」参照）
- `modules/rds/`: RDS for MySQL（master認証情報はwrite-only属性で管理。**ファイル作成・静的検査のみ完了、`terraform init`・lock更新・`plan`・`apply`はまだ未実行** — 詳細は後述の「3B: RDS MySQLの設計」参照）
- `environments/shared/`: 上記`ecr`モジュールのみを呼び出すルート構成（ECRはこの環境が唯一所有する。後述）
- `environments/production/`・`environments/staging/`: `network`・`s3`・`logging`・`iam`・`security`・`rds`モジュールを呼び出し、ECRは`data "aws_ecr_repository"`で参照するルート構成
- `bootstrap/create-state-bucket.sh`: Terraform state用S3バケットの作成・検証スクリプト

ALB本体・ACM・Route 53・ECS（Cluster/Task Definition/Service）は未実装です。レビュー後、段階的に追加します。

## ディレクトリ構成

```
infra/terraform/
├── bootstrap/                 # stateバケット作成スクリプト（Terraform管理外）
├── modules/
│   ├── network/
│   ├── ecr/
│   ├── s3/
│   ├── logging/
│   ├── iam/
│   ├── security/
│   └── rds/
└── environments/
    ├── shared/
    ├── production/
    └── staging/
```

## 第3段階の分割（3A〜3D）

継続課金の開始タイミングと依存関係を踏まえ、第3段階は一括実装せず4段階に分割します。

| 段階 | 対象 | apply時の継続課金 |
|---|---|---|
| 3A（実装済み） | Security Group（ALB/ECS Task/RDS）、RDS DB Subnet Group | 原則なし |
| 3B（ファイル作成済み・未apply） | RDS MySQL | 約$21/月〜（applyした時点から開始。設計・ファイル作成・静的検査の段階では課金なし） |
| 3C | ACM、Route 53、ALB | 約$25〜36/月〜 |
| 3D | ECS Cluster、Task Definition、Service | Fargate約$26.5/月〜 |

### 3A: Security Group・DB Subnet Groupの設計

`modules/security/`が作成するSecurity Group間の通信経路:

| SG | 方向 | プロトコル/ポート | 相手 |
|---|---|---|---|
| ALB | ingress | TCP 80 | `0.0.0.0/0`（HTTPSへのリダイレクト専用エントリポイント。リダイレクト自体の設定は3CのALBリスナーで行う） |
| ALB | ingress | TCP 443 | `0.0.0.0/0` |
| ALB | egress | TCP `container_port`（既定80） | ECS Task SG |
| ECS Task | ingress | TCP `container_port`（既定80） | ALB SG |
| ECS Task | egress | TCP 443 | `0.0.0.0/0`（ECR・SSM・CloudWatch Logs・S3・Translateの各AWS APIエンドポイント。SESは下記参照） |
| ECS Task | egress | TCP 3306 | RDS SG |
| RDS | ingress | TCP 3306 | ECS Task SG |
| RDS | egress | （なし） | — |

**DNS（53番）ルールは意図的に作りません。** VPCのAmazon提供DNS Resolver（各VPCのbase+2アドレス）宛のクエリはSecurity Groupのルール評価を経由しない特別経路のため、egressルールは不要です。

**SESの通信方式（HTTPS API or SMTP）は3Dまでに確定する未決課題です。** HTTPS API方式であれば上記443番のegressルールで既にカバーされ追加は不要です。SMTP方式を採用する場合は、その時点で必要なegressポート（587/465等）を個別に追加します。

**RDS SGにegressルールを作らない理由**: RDSインスタンス自身が能動的に外部接続を開始する通信は、現時点の設計では想定していません。将来、DBインスタンス自身が外部接続を開始する機能を採用する場合は、その通信要件を確認して必要なegressルールだけを追加します。

**実装・apply後の検証**: `terraform plan`および`aws ec2 describe-security-groups`で、各SG（ALB・ECS Task・RDS）について宣言したルール数と実際のルール数が一致し、意図しないallow-all egressルールが存在しないことを確認してください。

### 3B: RDS MySQLの設計

#### engine_versionの決定（MySQL 8.4.11）

当初候補だったMySQL 8.0.46は、AWS公式ドキュメント確認の結果**既にRDS Extended Support（追加課金）の対象**であることが判明したため不採用としました。代わりに**MySQL 8.4.11**を採用します（メジャーバージョン8.4のRDS標準サポート終了日は2029-07-31で、新規構築時点でExtended Support料金は発生しません。8.4.11自体のマイナーバージョン単位の標準サポート終了日は2027-08-21のため、その時期までに8.4系の新しいマイナーバージョンへの更新を計画してください）。

採用前にローカルDocker（`mysql:8.4.11`、既存の`docker-compose.yml`は変更せず一時的な別Compose project）でLaravel互換性を検証済みです:
- `SELECT VERSION()` = `8.4.11`
- `sql_mode`/`character_set_server`/`collation_server`は既存のMySQL 8.0環境と完全一致（差異なし）
- `php artisan migrate:fresh --seed`が全マイグレーション・シーダーで成功
- MySQL接続を明示した`php artisan test`が586件全件成功（2598 assertions）

#### `engine_lifecycle_support`の明示

`aws_db_instance`の`engine_lifecycle_support`は既定値`open-source-rds-extended-support`（Extended Supportへの自動移行を許可）のため、本プロジェクトでは明示的に`"open-source-rds-extended-support-disabled"`を設定します。2029年のメジャーバージョン標準サポート終了までにアップグレードしなかった場合、自動課金ではなくインスタンスの明示的な対応判断を強制する設計です。

#### Write-only属性によるmaster認証情報の管理

RDS master passwordは、Terraform 1.11+の write-only属性（`password_wo`/`password_wo_version`、SSMの`value_wo`/`value_wo_version`）と`ephemeral "random_password"`リソースの組み合わせで管理し、**plan・state・output・ログのいずれにも値を保存しません**。

```hcl
ephemeral "random_password" "db_master_password" {
  length           = 28
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}
```

`password_wo_version`（RDS）と`value_wo_version`（SSM）は共通の`db_password_version`変数で管理します。通常の`password`・`value`属性は使用しません。

**部分失敗時の復旧方針**（RDSとSSMが不一致になった場合）:
1. `db_password_version`を増やした上で`terraform plan`を実行し、`aws_db_instance.this`と`aws_ssm_parameter.db_master_password`の**両方**に変更が計画されていることを確認する
2. 片方しか変更対象にならない場合は`apply`を中止し、原因（state・AWS実体の不整合）を個別に調査する
3. `-target`による片側だけの更新は**禁止**
4. `apply`成功後は実際のDB接続確認まで行い、整合を確認する

#### SSM Parameter構成

| パラメータ名 | 型 | 値 |
|---|---|---|
| `/dananghelp/<environment>/DB_HOST` | String | RDSエンドポイント |
| `/dananghelp/<environment>/DB_PORT` | String | `3306` |
| `/dananghelp/<environment>/DB_DATABASE` | String | DB名 |
| `/dananghelp/<environment>/DB_MASTER_USERNAME` | String | masterユーザー名 |
| `/dananghelp/<environment>/DB_MASTER_PASSWORD` | SecureString（write-only） | masterパスワード |

通常アプリ用の`DB_USERNAME`・`DB_PASSWORD`（最小権限の専用DBユーザー）は3Bでは作成しません。3Dで確定します。

#### 公開ゲート: Task Execution Role分離（3D完了まで必須）

既存のECS Task Execution Roleは`ssm:GetParameters`を`/dananghelp/<environment>/*`のワイルドカードで許可しています。ECS Task Definition/Serviceがまだ存在しないため現時点で実害はありませんが、**以下の分離が完了するまで、本番ECS Serviceを起動してはいけません**。

- 通常Service用Execution Role: `DB_HOST`・`DB_PORT`・`DB_DATABASE`・アプリ専用`DB_USERNAME`・アプリ専用`DB_PASSWORD`のみ読み取り可能。`DB_MASTER_USERNAME`/`DB_MASTER_PASSWORD`への権限なし
- DB初期化用一時TaskのExecution Role: `DB_MASTER_USERNAME`・`DB_MASTER_PASSWORD`と必要な接続情報のみ読み取り可能。通常Serviceとは別のIAMロールとして新設
- 一時Task終了後もmaster認証情報をコンテナログへ出力しない

この分離は3Dの実装内容として必須対応とし、完了前のECS Service起動は行いません。

#### バックアップ・メンテナンスウィンドウ

production/stagingで重複しない時間帯を設定しています（いずれもダナン時間UTC+7で利用の少ない深夜帯）。

| 環境 | backup_window（UTC） | backup_window（ICT/ダナン） | maintenance_window（UTC） | maintenance_window（ICT/ダナン） |
|---|---|---|---|---|
| production | `19:00-19:30` | 02:00-02:30 | `mon:20:00-mon:21:00` | 月 03:00-04:00 |
| staging | `19:30-20:00` | 02:30-03:00 | `tue:20:30-tue:21:30` | 火 03:30-04:30 |

各環境内でもbackup windowとmaintenance windowは重複しません。

#### 削除時の必須手順（production）

`deletion_protection = true`をモジュール内に固定せず変数化しているため、通常の`destroy`は保護により失敗します。削除する場合は以下を順守してください。

1. 削除対象であることの事前レビュー（チケット・承認記録）
2. `rds_deletion_protection`を`false`へ変更し`apply`で反映
3. `rds_final_snapshot_identifier`の値が既存スナップショットと重複していないことを`aws rds describe-db-snapshots`で確認する
4. 上記すべて確認後にのみ`destroy`を実行する

## `shared`環境とapply順序（重要）

ECRリポジトリは`production`・`staging`の両方から同じイメージ（コミットSHAタグ）を参照できるよう、**`environments/shared/`が唯一所有**します。`production`・`staging`はそれぞれの状態から同じECRリポジトリを作成するのではなく、`data "aws_ecr_repository"`で名前引きして参照します（`terraform_remote_state`は使いません — 他環境のstateを丸ごと読める権限を渡さないための選択です）。

このため、**apply順序を必ず守ってください**。

1. 先に`environments/shared`を`init`・`plan`・`apply`し、ECRリポジトリを実際に作成する
2. その後、`environments/production`・`environments/staging`を`init`・`plan`・`apply`する

**`shared`のECRが未作成の時点で`production`・`staging`の`plan`を実行すると、`data "aws_ecr_repository"`の参照先が存在せず失敗します。これは想定どおりの正常な挙動であり、バグではありません。** `shared`を先にapplyしてから再実行してください。

`destroy`を行う場合は、この**逆順**です。先に`production`・`staging`を`destroy`し、その後`shared`を`destroy`してください（`production`・`staging`が参照している最中に`shared`のECRを先に消すと、参照先を失います）。

## 承認ゲート（必ず順守すること）

本番・検証環境問わず、以下の承認を得てから次へ進みます。

1. **stateバケット作成**（`bootstrap/create-state-bucket.sh`実行）は、実行前に明示的な承認を得る
2. **`terraform init -backend-config=backend.hcl`（remote backend初期化）** は、stateバケット作成後、別途の承認を得る
3. **`terraform plan`** は、コードレビュー後、別途の承認を得る
4. **`terraform apply`** は、`plan`の内容確認後、さらに別途の承認を得る

**`terraform init`・`plan`・`apply`、AWS CLIによるリソース作成・変更、SecureStringの投入は、このREADME作成時点ではまだ一切実行していません。**

## 実行順序

1. `terraform fmt -check -recursive`（フォーマット確認のみ、書き換えが必要な場合は`terraform fmt -recursive`で別途修正）
2. `.tf`ファイルの静的レビュー
3. AWS Pricing Calculator（calculator.aws）で0.5 vCPU/2GB構成の共有見積もりを作成し、[docs/RELEASE_PLAN.md 4.4節](../../docs/RELEASE_PLAN.md)の再計算額（約$86.61〜92.56/月）と大きく乖離しないことを確認する。税金・ドメイン登録料が含まれているかも分けて確認する
4. stateバケット名を確定する（グローバルに一意。`bootstrap/README.md`の手順で空き状況を確認。`shared`・`production`・`staging`の3環境で同じバケットを使い、`key`で分離する）
5. 各`environments/<env>/backend.hcl.example`（`shared`・`production`・`staging`の3つ）をコピーして`backend.hcl`を作成し、確定したバケット名を記入する（`backend.hcl`は`.gitignore`対象）
6. **承認を得た上で** `bootstrap/create-state-bucket.sh`を実行する
7. **承認を得た上で**、まず`environments/shared`で`terraform init -backend-config=backend.hcl`を実行する（上記「`shared`環境とapply順序」を参照 — ECRはここが唯一所有するため最初に適用する）
8. `terraform validate`（`shared`）
9. コードレビュー
10. **承認を得た上で** `terraform plan`（`shared`）
11. **承認を得た上で** `terraform apply`（`shared`）— これでECRリポジトリが作成される
12. **承認を得た上で**、`environments/production`・`environments/staging`それぞれで`terraform init -backend-config=backend.hcl`を実行する
13. `terraform validate`（production/staging）
14. コードレビュー
15. **承認を得た上で** `terraform plan`（production/staging）— `shared`が未applyの場合はここで失敗するのが正常
16. **承認を得た上で** `terraform apply`（production/staging）

## 環境ディレクトリを取り違えないための確認

`environments/shared/`・`environments/production/`・`environments/staging/`は見た目が似た構成です。作業時は必ず以下を確認してください。

- 今どちらのディレクトリにいるか（`pwd`）
- そのディレクトリの`backend.hcl`の`key`が、shared用は`"shared/terraform.tfstate"`、production用は`"production/terraform.tfstate"`、staging用は`"staging/terraform.tfstate"`になっていること（**目視確認を`terraform init`実行前に必ず行う**）
- いずれかの環境の`backend.hcl`を別環境へコピーしない

## backend.hclの作成

```
cd environments/shared   # または production / staging
cp backend.hcl.example backend.hcl
# backend.hcl を編集し、bucketに確定した一意な名前を記入する
```

`backend.hcl`は`.gitignore`対象のため、各担当者のローカル環境・CIのシークレットとして個別に用意します。

## AWS認証

`backend.tf`・`provider.tf`には認証情報を直接書きません。

- ローカル実行: `AWS_PROFILE`環境変数（IAM Identity Center/STSの一時認証情報、長期アクセスキーは使用しない）
- CI実行: GitHub Actions OIDCによる一時ロール

## Terraform/AWS Providerのバージョン

`versions.tf`の`aws`プロバイダ制約（`>= 6.68.0, < 7.0.0`）の下限6.68.0は、2026-10-14時点でhashicorp/terraform-provider-awsの最新リリースとして確認済みの値です（この下限は固定であり、以後の新リリースごとに引き上げるものではありません）。

`required_version`（Terraform CLI自体）は3B（RDS, write-only属性利用）に合わせて**`>= 1.11.0, < 2.0.0`**へ変更しました（1.11.0はwrite-only属性: `password_wo`/`value_wo`を初めて導入したリリース、2025-02-27）。`shared`環境もwrite-only属性自体は使いませんが、3環境で同じTerraform CLIバージョンを使う既存方針に合わせて同じ下限へ揃えています。**実際に検証コンテナとして使用するバージョンは`hashicorp/terraform:1.17.1`**（2026-10-14時点の最新安定版）で固定します。`required_version`の範囲はあくまで互換性の許容幅であり、実行する具体的なバージョンは別途このように固定します。

`production`・`staging`には`hashicorp/random`プロバイダを追加しました（`>= 3.7.0, < 4.0.0`。3.7.0は`ephemeral "random_password"`リソースを初めて導入したリリース、2025-02-25）。`modules/rds`のmaster password生成に使用します。

`shared`・`production`・`staging`の3環境で`terraform init -backend=false`を実行済みで、`aws`プロバイダについてこの制約の範囲内で実際に解決されたバージョンは**6.69.0**です。各環境の`.terraform.lock.hcl`にこのバージョンが固定されており、Gitにコミットします（`.terraform/`自体は`.gitignore`対象です）。**`random`プロバイダ追加後のlockファイル更新、および`required_version`変更後の`terraform init`は3Bのこのラウンドではまだ実行していません**（ファイル作成・静的検査のみ）。`terraform plan`/`apply`に向けて別途backendでの`init`を行う際も、この制約・lockファイルの前提は変わりません。
