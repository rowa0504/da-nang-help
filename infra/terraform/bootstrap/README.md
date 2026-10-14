# Terraform stateバケットのbootstrap

`create-state-bucket.sh`は、Terraformのstateを保存するS3バケットを作成・検証するスクリプトです。Terraformのbackend自体がこのバケットに依存するため、Terraform管理対象外（循環を避けるため）とし、このスクリプトで一度だけ作成します。

## 前提

- 実行には、IAM Identity Center/STSの一時認証情報（長期アクセスキーではない）でログインしたAWS CLIプロファイルが必要です
- バケット名はS3全体でグローバルに一意である必要があります。候補名を決めたら、実行前に以下で空き状況を確認してください（確実な判定ではなく参考情報です。最終的な確定は、このスクリプト自体の実行結果をもって行います）:
  ```
  aws s3api head-bucket --bucket <候補名> --profile <profile名> --no-cli-pager
  ```

## HeadBucketの判定表

AWSのHeadBucketは、バケットの状態に応じて200・403・404に加え、301や400等を返すことがあります。終了コードだけで「存在しない＝404」と判断せず、エラーメッセージ中のHTTPステータスコードを確認して分岐します（スクリプト内のコメントと同一の表）。

| HeadBucket結果 | 動作 |
|---|---|
| 200（成功） | 自分のアカウント所有として、リージョン確認・設定の検証・補完へ進む |
| 404（明示的なNot Found） | 新規作成へ進む |
| 403（Forbidden） | 他アカウント所有、または権限不足。即時中断、何も変更しない |
| 301・400・その他の解析不能なエラー | あいまいな結果として、新規作成せず即時中断 |

`head-bucket`には`--expected-bucket-owner <自分のaccount ID>`を渡します。バケットが存在しても所有者が自分のアカウントと異なる場合、AWSは403を返すため、「存在するが他アカウント所有」のケースを確実に検出できます。

200の場合はさらに`get-bucket-location`で既存バケットのリージョンが`AWS_REGION`と一致することを確認し、不一致なら中断します。

## 実行方法

```
STATE_BUCKET_NAME=dananghelp-terraform-state-<一意なsuffix> \
AWS_REGION=ap-southeast-1 \
AWS_PROFILE=danang-help-admin \
./create-state-bucket.sh
```

## このスクリプトが行うこと

1. `aws sts get-caller-identity`で対象アカウント・account IDを表示（実行前に目視確認すること）
2. `head-bucket --expected-bucket-owner <account ID>`で既存バケットの有無・所有者を確認し、上記判定表に従って分岐
3. （200の場合）`get-bucket-location`でリージョン一致を確認
4. 以下を設定（既に正しい場合も毎回再適用され、設定ドリフトを検知・修復する。すべて`--expected-bucket-owner`付き）:
   - Versioning有効化
   - デフォルト暗号化（SSE-S3）
   - Public Access Block（4項目すべて有効）
   - Object Ownership Controls（`BucketOwnerEnforced`、ACLを無効化し所有権をバケット所有者へ統一）
   - TLS強制バケットポリシー（`aws:SecureTransport=false`を拒否）
   - lifecycle policy（非現行バージョンを365日後に削除。現行版は対象外）
5. 設定結果を`get-*`系コマンドで読み戻して確認表示

## このスクリプトが行わないこと

- バケットの削除（削除コードパスは一切存在しません）
- オブジェクトの削除
- 秘密情報の出力・ログ記録（取り扱う情報はバケット設定とaccount IDのみ）

## 用途

**このバケットはTerraform state保存専用です。削除しないでください。他の用途で使用しないでください。**
