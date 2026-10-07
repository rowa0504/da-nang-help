# Da Nang Help — バックログ

Draft v0.4（[BLUEPRINT.md](BLUEPRINT.md)）で「MVP機能実装完了」と位置づけた時点で、まだ対応していない項目を一覧化したものです。本ドキュメントは「何が残っているか」の管理に専念し、対応順序・進め方は`docs/RELEASE_PLAN.md`（別紙）で扱います。

各項目には次の4項目を付与します。

- **状態**: 未着手 / 一部実装 / 検討中
- **優先度**: P0（本番公開前に必須）/ P1（ソフトローンチ後、優先度高）/ P2（将来拡張）
- **関連FR/NFR**: BLUEPRINT.md上の対応箇所
- **完了条件**: 「完了した」と判断できる具体的な基準

---

## P0 — 本番公開前に必須

### P0-1. 書き込み系エンドポイントのレート制限
- 状態: 未着手（ログイン試行の制限のみ実装済み。email+IPキーで5回/60秒、[LoginRequest.php](../app/Http/Requests/Auth/LoginRequest.php)）
- 優先度: P0
- 関連FR/NFR: [10 非機能要件](BLUEPRINT.md#10-非機能要件)（セキュリティ行）
- 完了条件: 以下すべてを満たす
  - 対象エンドポイント（登録・依頼投稿・Offer送信・写真アップロード等）を決定している
  - エンドポイントごとにユーザー単位／IP単位のどちらで制限するかを使い分けている（未ログイン操作はIP単位、ログイン後の書き込みはユーザー単位を基本とする等）
  - 上限値と時間枠（例: N回／M秒）を決定・設定している
  - 上限超過時にHTTP 429を返す
  - 上限超過時のエラーメッセージがen/ja/viで表示される
  - 上記を検証するFeatureテストが存在する
  - 上限超過時は、翻訳キュー投入や写真のStorage保存より前に拒否される（無駄な副作用を発生させない）

### P0-2. Amazon Translate実装
- 状態: 実装済み・AWS疎通確認待ち（`AwsTranslateTranslator`・`TRANSLATOR_DRIVER`環境変数による切り替え・Job側のリトライ（3回・60秒）・エラー分類・`MaxUtf8Bytes`バリデーションルールまで実装・単体/Featureテスト済み。IAM Identity Center/STSの一時認証情報を用いた実Amazon Translate APIへの疎通確認はまだ実施していないため「完了」にはしない）
- 優先度: P0
- 関連FR/NFR: FR-18〜23a、[15 技術アーキテクチャ案](BLUEPRINT.md#15-技術アーキテクチャ案)「機械翻訳」
- 完了条件: 本番環境で`TRANSLATOR_DRIVER=aws`に切り替え、ECS Task Role経由でのアクセス・文字数上限・レート制限・予算アラートが機能することを実AWS接続で確認する

### P0-3. AWSインフラ（Stage 1）
- 状態: 未着手（ローカル`docker-compose.yml`のみ存在、IaC・Terraform等なし）
- 優先度: P0
- 関連FR/NFR: [18 AWS構成案](BLUEPRINT.md#18-aws構成案)
- 完了条件: ECS Fargate（Nginx/PHP-FPM/Worker/Scheduler）・ALB・RDS（Single-AZ）・private S3・Parameter Store・CloudWatch LogsがBLUEPRINT記載の構成で稼働する

### P0-4. CI/CD
- 状態: 未着手（`.github/workflows`等のCI定義が存在しない）
- 優先度: P0
- 関連FR/NFR: [19 開発ロードマップ](BLUEPRINT.md#19-開発ロードマップ)Phase 10
- 完了条件: push/PR時にPHPUnit・Vitest・`tsc --noEmit`・lintが自動実行され、デプロイパイプライン（ビルド→ECRプッシュ→ECS更新）が存在する

### P0-5. 本番用環境変数と秘密情報管理
- 状態: 未着手
- 優先度: P0
- 関連FR/NFR: [10 非機能要件](BLUEPRINT.md#10-非機能要件)「コスト管理」
- 完了条件: `.env`をリポジトリ・イメージへ含めず、Parameter Store（Standard）経由で本番環境変数・秘密情報を注入できる

### P0-6. 本番キューWorkerとSchedulerの常駐設定
- 状態: 未着手（ローカルは`--stop-when-empty`での手動実行が中心）
- 優先度: P0
- 関連FR/NFR: [18 AWS構成案](BLUEPRINT.md#18-aws構成案)
- 完了条件: `queue:work`・`schedule:work`が本番Task内で常駐プロセスとして稼働し、異常終了時にTaskが再起動する

### P0-7. Storageの公開・署名付きURL・CORS設定
- 状態: 未着手
- 優先度: P0
- 関連FR/NFR: FR-17、[10 非機能要件](BLUEPRINT.md#10-非機能要件)「画像プライバシー」
- 完了条件: 本番S3バケットがprivateで、画像表示時に期限付き署名URLが発行され、フロントエンドからのCORSアクセスが正しく制限される

### P0-8. メール送信ドメインの認証
- 状態: 未着手（開発はMailpit）
- 優先度: P0
- 関連FR/NFR: FR-43
- 完了条件: 本番送信ドメインでSPF/DKIM/DMARCが設定され、主要メールクライアントで迷惑メール判定されないことを確認する

### P0-9. データベースバックアップと復元テスト
- 状態: 未着手
- 優先度: P0
- 関連FR/NFR: [10 非機能要件](BLUEPRINT.md#10-非機能要件)「可用性」
- 完了条件: RDSの自動バックアップが有効で、実際にスナップショットからの復元手順を一度検証している

### P0-10. エラー監視・ヘルスチェック・アラート
- 状態: 未着手
- 優先度: P0
- 関連FR/NFR: [10 非機能要件](BLUEPRINT.md#10-非機能要件)「可観測性」
- 完了条件: 致命的エラーがCloudWatch等へ集約され、アラート通知が届く。ALBのヘルスチェックが正しくTaskの異常を検知する

### P0-11. 利用規約・プライバシーポリシー
- 状態: 未着手
- 優先度: P0
- 関連FR/NFR: [10 非機能要件](BLUEPRINT.md#10-非機能要件)「プライバシー」
- 完了条件: en/ja/viで利用規約・プライバシーポリシーが用意され、登録フローから閲覧・同意できる

### P0-12. 管理者アカウントの安全な初期作成手順
- 状態: 未着手（開発はtinker等での直接作成）
- 優先度: P0
- 関連FR/NFR: FR-44〜46
- 完了条件: 本番初回Adminアカウントを、パスワードをリポジトリ・ログへ残さない手順で作成できる運用手順が文書化されている

### P0-13. 本番SeederでDemoデータを作成しないことの確認
- 状態: 未着手
- 優先度: P0
- 関連FR/NFR: —
- 完了条件: 本番デプロイ時に`CategorySeeder`等の必須マスタ投入のみが実行され、開発用のDemo Provider等のダミーデータが本番DBへ作成されないことをデプロイ手順上で保証する

### P0-14. README／運用手順
- 状態: 未着手（`README.md`は空）
- 優先度: P0
- 関連FR/NFR: —
- 完了条件: セットアップ手順（Docker Compose起動、マイグレーション、Seeder）・主要な運用コマンド（キュー、スケジューラ、バックアップ）が`README.md`に記載されている

---

## P1 — ソフトローンチ後、優先度高

### P1-1. 韓国語・中国語・ロシア語対応
- 状態: 未着手
- 優先度: P1
- 関連FR/NFR: FR-04、[10 非機能要件](BLUEPRINT.md#10-非機能要件)「多言語対応」
- 完了条件: 単にUI辞書（`resources/js/lang/*.ts`）へ言語を追加するだけでなく、以下すべてに反映されている
  - `users.locale`・`service_requests.source_locale`・`offers.source_locale`等のDBカラム長・Enum・バリデーションルールの対応言語拡張
  - 機械翻訳対象言語（`service_request_translations`/`offer_translations`の`locale`）への追加
  - `Intl.DisplayNames`による言語名表示の確認（追加言語がブラウザ側で正しく解決されるか）
  - バックエンドの通知メール（`lang/{locale}/*.php`）
  - `category_translations`／エリア名の翻訳データ投入

### P1-2. Google Maps・住所検索連携
- 状態: 未着手（`lat`/`lng`はnullable列として保持済み、[BLUEPRINT.md](BLUEPRINT.md#16-データベース--er設計案)参照）
- 優先度: P1
- 関連FR/NFR: FR-09
- 完了条件: 住所入力時にGoogle Maps等で座標をバックフィルでき、既存の`address_text`中心の運用を壊さない

### P1-3. Providerの公開プロフィール
- 状態: 未着手
- 優先度: P1
- 関連FR/NFR: FR-41
- 完了条件: Customerが依頼前にProviderの評価・完了件数等を一覧・詳細で確認できる公開ページが存在する

### P1-4. 監査ログ
- 状態: 未着手
- 優先度: P1
- 関連FR/NFR: [17 API設計案](BLUEPRINT.md#17-api設計案)「認可設計」（Admin行の将来拡張として言及）
- 完了条件: Adminによる審査・モデレーション操作が誰によっていつ行われたか追跡できる

### P1-5. 通知改善
- 状態: 未着手
- 優先度: P1
- 関連FR/NFR: [20 未確定事項](BLUEPRINT.md#20-未確定事項確認したいこと)
- 完了条件: メール以外の代替・補完通知チャネル（Zalo/SMS等）の必要性を検証し、必要であれば導入する

### P1-6. 双方向レビュー（Provider→Customer）
- 状態: 未着手
- 優先度: P1
- 関連FR/NFR: FR-40〜42、[16 データベース / ER設計案](BLUEPRINT.md#16-データベース--er設計案)「DB制約」の`reviews`行
- 現行仕様: Customer→Providerの一方向のみ（`CreateReviewRequest`がCustomerロール限定、`reviews.job_id`にUNIQUE制約）
- 完了条件: 以下すべてを満たす
  - ProviderからCustomerへのレビュー投稿を可能にする
  - `reviews`のUNIQUE制約を`job_id`単独から適切な複合制約（`job_id`+評価方向、または`job_id`+`rater_id`）へ変更する
  - raterとrateeが、対象Jobの当事者（そのJobのCustomerまたはProvider本人）であることを検証する
  - 自己レビュー（rater = ratee）を拒否する
  - 同一方向（同じJob・同じraterからの2件目）の重複レビューを拒否する
  - Customer→ProviderとProvider→Customerを、同一Jobに対して各1件ずつ許可する
  - 双方向それぞれの平均評価の算出方法（Provider側の`avg_rating`と、新設するCustomer側の評価指標)と、その表示先画面を定義する
  - マイグレーションのup/downをテストする（既存レビューを保持したまま、UNIQUE制約の変更のみで移行できること）
  - 上記すべてを検証するFeatureテストを追加する
- 備考: スキーマ変更（UNIQUE制約の変更）を伴うため、今回のMVP（v0.1.0）には含めない

---

## P2 — 将来拡張

### P2-1. 決済・エスクロー
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [08 スコープ外機能](BLUEPRINT.md#08-スコープ外機能mvp対象外)、[14 将来的な拡張機能](BLUEPRINT.md#14-将来的な拡張機能)
- 完了条件: —（本ドラフトのスコープ外。導入時は別途設計）

### P2-2. リアルタイムチャット
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [08 スコープ外機能](BLUEPRINT.md#08-スコープ外機能mvp対象外)
- 完了条件: —

### P2-3. 本人確認（身分証明書のオンライン照合）
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [08 スコープ外機能](BLUEPRINT.md#08-スコープ外機能mvp対象外)
- 完了条件: —

### P2-4. AIによる自動マッチング・価格推定
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [08 スコープ外機能](BLUEPRINT.md#08-スコープ外機能mvp対象外)
- 完了条件: —

### P2-5. ネイティブモバイルアプリ
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [08 スコープ外機能](BLUEPRINT.md#08-スコープ外機能mvp対象外)
- 完了条件: —

### P2-6. サブスクリプション課金
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [11 収益モデルの候補](BLUEPRINT.md#11-収益モデルの候補)
- 完了条件: —

### P2-7. 多都市展開
- 状態: 未着手
- 優先度: P2
- 関連FR/NFR: [20 未確定事項](BLUEPRINT.md#20-未確定事項確認したいこと)
- 完了条件: `areas`テーブルへの都市（city）概念の追加設計を含む
