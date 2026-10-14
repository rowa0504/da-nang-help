# Terraform — Da Nang Help AWS基盤

このディレクトリはAWSインフラをTerraformで管理するためのコードです。詳細な方針・コスト試算・state管理設計は[docs/RELEASE_PLAN.md](../../docs/RELEASE_PLAN.md)の4節を参照してください（本READMEは実行手順に専念します）。

## 現在の実装範囲（第1段階）

- `modules/network/`: VPC、public subnet×2、private subnet×2、Internet Gateway、ルートテーブル（NAT Gatewayなし）
- `environments/production/`・`environments/staging/`: 上記networkモジュールを呼び出すルート構成
- `bootstrap/create-state-bucket.sh`: Terraform state用S3バケットの作成・検証スクリプト

IAM・RDS・ECS・ALB・Route 53等は未実装です。レビュー後、段階的に追加します。

## ディレクトリ構成

```
infra/terraform/
├── bootstrap/                 # stateバケット作成スクリプト（Terraform管理外）
├── modules/
│   └── network/
└── environments/
    ├── production/
    └── staging/
```

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
4. stateバケット名を確定する（グローバルに一意。`bootstrap/README.md`の手順で空き状況を確認）
5. 各`environments/<env>/backend.hcl.example`をコピーして`backend.hcl`を作成し、確定したバケット名を記入する（`backend.hcl`は`.gitignore`対象）
6. **承認を得た上で** `bootstrap/create-state-bucket.sh`を実行する
7. **承認を得た上で** 各環境ディレクトリで`terraform init -backend-config=backend.hcl`を実行する
8. `terraform validate`
9. コードレビュー
10. **承認を得た上で** `terraform plan`
11. **承認を得た上で** `terraform apply`

## 環境ディレクトリを取り違えないための確認

`environments/production/`と`environments/staging/`は見た目が同じ構成（同じ8ファイル）です。作業時は必ず以下を確認してください。

- 今どちらのディレクトリにいるか（`pwd`）
- そのディレクトリの`backend.hcl`の`key`が、production用は`"production/terraform.tfstate"`、staging用は`"staging/terraform.tfstate"`になっていること（**目視確認を`terraform init`実行前に必ず行う**）
- `environments/production/`の`backend.hcl`を`environments/staging/`へコピーする、またはその逆を行わない

## backend.hclの作成

```
cd environments/production   # またはstaging
cp backend.hcl.example backend.hcl
# backend.hcl を編集し、bucketに確定した一意な名前を記入する
```

`backend.hcl`は`.gitignore`対象のため、各担当者のローカル環境・CIのシークレットとして個別に用意します。

## AWS認証

`backend.tf`・`provider.tf`には認証情報を直接書きません。

- ローカル実行: `AWS_PROFILE`環境変数（IAM Identity Center/STSの一時認証情報、長期アクセスキーは使用しない）
- CI実行: GitHub Actions OIDCによる一時ロール

## Terraform/AWS Providerのバージョン

`versions.tf`の`aws`プロバイダ制約（`>= 6.68.0, < 7.0.0`）は、2026-10-14時点でhashicorp/terraform-provider-awsの最新リリースとして確認済みの値です。`required_version`（Terraform CLI自体）は`>= 1.10.0, < 2.0.0`です。**最初の`terraform init`を実行する前に**、これらの制約がその時点でのローカル環境・最新リリースと大きくずれていないか再確認してください。`terraform init`を一度も実行していないため、`.terraform.lock.hcl`はまだ存在しません（この段階では正常です。init後、実際に解決されたバージョンをこのファイルへ固定します）。
