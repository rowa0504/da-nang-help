# Terraform — Da Nang Help AWS基盤

このディレクトリはAWSインフラをTerraformで管理するためのコードです。詳細な方針・コスト試算・state管理設計は[docs/RELEASE_PLAN.md](../../docs/RELEASE_PLAN.md)の4節を参照してください（本READMEは実行手順に専念します）。

## 現在の実装範囲（第1〜2段階）

- `modules/network/`: VPC、public subnet×2、private subnet×2、Internet Gateway、ルートテーブル（NAT Gatewayなし）
- `modules/ecr/`: コンテナイメージ用ECRリポジトリ（IMMUTABLE・scan_on_push・lifecycle policy）
- `modules/s3/`: Service Request写真用S3バケット（TLS強制・BucketOwnerEnforced・SSE-S3）
- `modules/logging/`: コンテナ（nginx/php-fpm/worker/scheduler）ごとのCloudWatch Log Group
- `modules/iam/`: ECS Task Execution Role・Task Role
- `environments/shared/`: 上記`ecr`モジュールのみを呼び出すルート構成（ECRはこの環境が唯一所有する。後述）
- `environments/production/`・`environments/staging/`: `network`・`s3`・`logging`・`iam`モジュールを呼び出し、ECRは`data "aws_ecr_repository"`で参照するルート構成
- `bootstrap/create-state-bucket.sh`: Terraform state用S3バケットの作成・検証スクリプト

RDS・ECS・ALB・Route 53等は未実装です。レビュー後、段階的に追加します。

## ディレクトリ構成

```
infra/terraform/
├── bootstrap/                 # stateバケット作成スクリプト（Terraform管理外）
├── modules/
│   ├── network/
│   ├── ecr/
│   ├── s3/
│   ├── logging/
│   └── iam/
└── environments/
    ├── shared/
    ├── production/
    └── staging/
```

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

`versions.tf`の`aws`プロバイダ制約（`>= 6.68.0, < 7.0.0`）の下限6.68.0は、2026-10-14時点でhashicorp/terraform-provider-awsの最新リリースとして確認済みの値です（この下限は固定であり、以後の新リリースごとに引き上げるものではありません）。`required_version`（Terraform CLI自体）は`>= 1.10.0, < 2.0.0`です。

`shared`・`production`・`staging`の3環境で`terraform init -backend=false`を実行済みで、この制約の範囲内で実際に解決されたバージョンは**6.69.0**です。各環境の`.terraform.lock.hcl`にこのバージョンが固定されており、Gitにコミットします（`.terraform/`自体は`.gitignore`対象です）。`terraform plan`/`apply`に向けて別途backendでの`init`を行う際も、この制約・lockファイルの前提は変わりません。
