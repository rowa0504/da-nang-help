# Da Nang Help — 本番公開計画（RELEASE_PLAN）

**作成: 2026-10-02 — 対象: v0.1.0（MVP機能実装完了）からソフトローンチまで**

## 1. 本ドキュメントの位置づけ

- [`docs/BLUEPRINT.md`](BLUEPRINT.md)：要件・設計そのもの（何を作ったか）
- [`docs/BACKLOG.md`](BACKLOG.md)：何が残っているか（P0/P1/P2の一覧）
- **本ドキュメント（RELEASE_PLAN.md）**：BACKLOGのP0項目を**どの順番で・どう本番公開まで持っていくか**

v0.1.0タグ（commit `8223eb1`）はMVP機能実装完了のチェックポイントとして**動かしません**。本ドキュメントに沿った本番公開準備が完了した時点で、新規に`v0.1.1`タグを作成します。

## 2. 前提とスコープ

- 対象はダナンでの**限定エリア・限定カテゴリからのソフトローンチ**（[12 戦略的考察](BLUEPRINT.md#12-戦略的考察)のGo-to-market戦略）。全国・全カテゴリへの一般公開ではありません。
- 利用者・収益がまだ存在しない実験段階であるため、[10 非機能要件](BLUEPRINT.md#10-非機能要件)「コスト管理（Stage 1）」の方針（Multi-AZ・Auto Scaling・Redis・SQS・CloudFront・WAF・NAT Gatewayは不採用）を踏襲します。
- 本ドキュメントは**AWSインフラをまだ1つも作成していない**時点から書き始めています。

### 2.1 着手前提チェックリスト（フェーズB開始前に必須、「スコープ外」にしない）

AWSアカウントまわりの基礎設定は、曖昧に「前提条件」として済ませず、フェーズB着手前の明示的なゲートとして扱います。

- [ ] AWSアカウントのrootユーザーにMFAを設定済み
- [ ] 請求アラート（コンソールの基本的な請求通知。4.5節のAWS Budgetsとは別に、アカウント全体の異常検知として設定）
- [ ] IAM管理方針を決定済み（個人の長期アクセスキーを作らない。4.6節参照）
- [ ] 請求用の連絡先・支払い方法が有効

## 3. フェーズA — アプリ内P0対応（AWSインフラに依存しない）

AWSインフラの有無に関わらず着手できる項目です。インフラ構築（フェーズB）と並行して進められます。

| 項目 | 内容 | 完了条件（BACKLOG.mdより） |
|---|---|---|
| A-1 書き込み系エンドポイントのレート制限 | 登録・依頼投稿・Offer送信・写真アップロード等 | [BACKLOG.md P0-1](BACKLOG.md#p0-1-書き込み系エンドポイントのレート制限) |
| A-2 Amazon Translate実装 | `FakeTranslator`→本番実装への差し替え。AWSインフラ構築前でも疎通確認・実装・自動テストは可能。**IAM Identity Centerまたは一時認証情報（STSの一時クレデンシャル）を利用して先行検証する。長期アクセスキーを持つ開発用IAMユーザーは原則作成しない**（詳細は4.6節） | [BACKLOG.md P0-2](BACKLOG.md#p0-2-amazon-translate実装) |
| A-3 利用規約・プライバシーポリシー | en/ja/vi 3言語。実装・自動テストは完了済み（登録フローでの年齢確認・法的文書への同意、`config/legal.php`によるversion/effective_date/governing_localeの一元管理、`php artisan legal:check`による本番公開前チェック）。**草案段階では専門家未レビューであることを本ドキュメント上で記録する。実ユーザーを受け入れるソフトローンチ前に、ベトナム法・個人情報の越境処理（政令356/2025/ND-CPに基づくDPIA・越境移転影響評価）を含め、必要に応じて専門家の確認を受ける**（未レビューである旨を公開ポリシー本文へ表示する必要はない）。運営者名・問い合わせ先・発効日・準拠法上の管轄の確定も、legal:checkが合格する前提として別途必要 | [BACKLOG.md P0-11](BACKLOG.md#p0-11-利用規約プライバシーポリシー) |

この3項目は、フェーズB（AWS基盤）の着手を待たずに先行して計画・実装を進めて構いません。

## 4. フェーズB — AWS基盤方針

### 4.1 確定事項（今回の合意）

| 論点 | 結論 |
|---|---|
| コンピュート方式 | **Fargate + ALBを維持**。EC2への切り替えは行わない |
| 理由 | EC2との差額（概算$5〜25/月）よりも、ECS/Fargate/ALBの実践学習の価値を優先。月額の差分で運用負荷（OSパッチ・Docker自前管理・障害復旧の自前実装）を引き受けるコストの方が大きいと判断 |
| 月額予算 | **通常目標 $90/月以内、上限 $100/月**（異常時含む）。4.4節のAWS Price List API実単価再計算により、初回構築サイズを0.5vCPU/2GBとすることで通常時この範囲に収まる見込み |
| NAT Gateway | **Stage 1では不採用**（public subnet + Task Public IP + Security Groupで代替） |
| 常設ステージング環境 | **作らない**。検証が必要な時だけ一時的に構築し、終わったら削除してコストを抑える |
| インフラ作成前の必須ゲート | **AWS Pricing Calculatorによる最終見積もり**を取得し、下記4.4の月額目安と大きく乖離しないことを確認してから着手する |

### 4.2 構成（Stage 1）

[18 AWS構成案](BLUEPRINT.md#18-aws構成案)のStage 1方針をそのまま採用し、以下を基本構成とします。

```mermaid
flowchart LR
  U["利用者"] --> R53["Route 53"]
  R53 --> ALB["ALB (public subnet)"]
  ALB --> NGINX
  subgraph TASK["Fargate Task (public subnet, Public IP)"]
    NGINX["Nginx"] -->|127.0.0.1:9000| PHPFPM["PHP-FPM"]
    WORKER["Queue Worker"]
    SCHED["Scheduler"]
  end
  PHPFPM --> RDS[("RDS MySQL (private subnet, Single-AZ)")]
  WORKER --> RDS
  PHPFPM --> S3[("S3 private — 依頼写真")]
  WORKER --> S3
  WORKER --> TRANSLATE["Amazon Translate"]
  TASK --> SM["Parameter Store"]
  TASK --> CW["CloudWatch Logs"]
  ECR["ECR"] -.->|イメージpull| TASK
```

- **ALB**: public subnet。本番Taskのポートのみへルーティング
- **Fargate Task**: public subnet + Public IP。Security GroupはALBからの通信のみ受信
- **RDS**: private subnet、Fargateからのみ接続許可
- **S3**: 依頼写真（private、object_key管理、署名付きURL）
- **ECR**: コンテナイメージ
- **Parameter Store**: 秘密情報（Standard tier）
- **CloudWatch**: ログ・監視
- **採用しないもの**: CloudFront、ElastiCache（Redis）、NAT Gateway、WAF、Multi-AZ、Auto Scaling

### 4.3 本番Taskの構成見直し（今回の確定事項）

現在のローカル開発用`docker-compose.yml`は`node`（フロントエンドビルド用）と`mailpit`（開発用メールキャッチャー）をサービスとして含んでいますが、**これらは本番Taskには含めません**。

| サービス | 開発環境 | 本番Task |
|---|---|---|
| Nginx | ✅ | ✅ |
| PHP-FPM | ✅ | ✅ |
| Queue Worker | ❌（手動実行） | ✅ 常駐 |
| Scheduler | ❌（手動実行） | ✅ 常駐 |
| Node (Vite) | ✅ 開発サーバー | ❌ — **CIのDockerビルド時にフロントをビルドし、`public/build/`の成果物だけを本番イメージへ含める**（マルチステージビルド） |
| MySQL | ✅（コンテナ） | ❌ — RDSを使用 |
| Mailpit | ✅ | ❌ — **Amazon SES等へ置換**（本番メール送信ドメイン認証はフェーズC） |

本番ランタイムは**Nginx・PHP-FPM・Queue Worker・Schedulerの4プロセス**で構成します。

**単一Task内4コンテナ vs Workerを別ECS Serviceに分離するか**については、[BLUEPRINT.md 18節](BLUEPRINT.md#ecs構成-単一task内の4コンテナv031で変更)の既存方針（コスト最優先のStage 1では単一Task・単一Serviceへ統合）をそのまま踏襲し、**単一Task内4コンテナ構成を採用**します。Worker分離は[BLUEPRINT.md「Stage 2以降へ先送りするもの」](BLUEPRINT.md#stage-1--stage-2-の境界)に既に明記されているStage 2移行トリガー（WebとWorkerがCPU/メモリを取り合うようになった等）が実際に発生してから検討します。新たな判断は不要です。

**可用性に関する明示的な割り切り**: ECS ServiceはStage 1では`desiredCount=1`とします。単一障害点であること、Task入れ替え（デプロイ・異常終了からの再起動）の間は短時間の停止が発生することを許容します。各コンテナ（Nginx・PHP-FPM・Worker・Scheduler）は`essential: true`とし、いずれか1つでも停止するとTask全体が再作成される既存のBLUEPRINT方針（低トラフィック期はこの再起動時の短時間欠落を許容し、複雑な障害分離は導入しない）を踏襲します。

**Nginx用イメージとPHP-FPM用イメージで、同一コミットのフロントエンド資産を確実に一致させる方法**: Nginxコンテナ（静的ファイルを配信）とPHP-FPMコンテナ（Viteのmanifest.jsonを参照してタグを生成）は別イメージ・別ファイルシステムのため、ビルド元のGit commitが食い違うと配信ファイルとHTMLが参照するファイル名が不一致を起こし得ます。これを避けるため、CI/CDパイプライン内で**フロントエンド資産（`public/build/`）を1回だけビルドし、同一のビルド成果物をNginxイメージ・PHP-FPMイメージ双方のビルドコンテキストへコピーしてから、両イメージに同じGit commit SHAタグを付けて同時にpushする**（詳細はC-1）。

**現状のDockerビルド基盤**: `docker/php/Dockerfile`には`dev`ターゲット（ローカル開発用）に加え、本番ECRイメージを見据えた`build`ターゲット（`composer install --no-dev`、autoload最適化）が既に用意されています。ただし以下は未実装です。

- フロントエンド資産（Vite）のビルドをこのDockerfileのマルチステージに統合すること
- `docker/nginx/default.conf`を単一Fargate Task向け（`fastcgi_pass 127.0.0.1:9000`、現在は`php:9000`というdocker-compose内のサービス名を使用）に調整すること
- 最終的な軽量ランタイムステージ（開発ツール・composer.lock等を含まない）の追加

これらはフェーズCで実装します。

### 4.3.1 初期Fargateサイズと増量方針（今回の確定事項）

4.4節のコスト再計算に基づき、**初期Taskサイズは0.5 vCPU / 2GBとします**（AWS公式仕様上、0.5 vCPU（512 CPU units）は1GB・2GB・3GB・4GBのいずれのメモリも選択可能）。4コンテナ（Nginx・PHP-FPM・Queue Worker・画像処理を伴うPHP-FPM）構成では、CPU不足よりもメモリ不足が先に問題化しやすいため、CPUを最小の0.5 vCPUに保ったままメモリだけを2GBへ上げる構成が、コストと安全余裕のバランスが最も良い開始点です。

- **desiredCount = 1**（4.3節の可用性の割り切りを継続）
- 各コンテナ（Nginx・PHP-FPM・Worker・Scheduler）に`memory`（limit）と`memoryReservation`（soft limit）を設定し、Task全体のメモリをコンテナ間でどう配分するかを明示する
- CloudWatchで**Task CPU Utilization・Task Memory Utilization・Taskの停止理由（`stoppedReason`、特にOOM Killによる停止）**を監視する（D-2のエラー監視・ヘルスチェックと合わせて実施）
- **増量が必要になった場合の判断基準**:
  - メモリ使用率が高止まりする、またはOOM Killによる停止が発生した場合 → まず**0.5 vCPU / 3GBまたは4GB**へ増量する（CPUは変更しない）
  - CPU使用率が高止まりする場合に限り → **1 vCPU / 2GB以上**へ変更する
- 先にCPUを上げる、または1 vCPU/2GBから開始する判断は行いません（4.4節の再計算により$100/月の上限を超えるリスクが高いため）

### 4.4 コスト試算（Singapore/ap-southeast-1）

当初の概算をAWS公式Price List API（calculator.aws・各サービスの公式価格ページと同一の正規データソース、取得日2026-10-14）の実単価で再計算しました。この再計算は構成選定に十分な精度がありますが、**RELEASE_PLAN.mdが必須ゲートとしているcalculator.awsでの共有見積もり作成自体は省略せず、Terraform適用直前の最終ダブルチェックとして別途実施します**（4.6節参照）。

取得した実単価（ap-southeast-1）: Fargate $0.050560/vCPU時間・$0.005530/GB時間、ALB $0.0252/時間+$0.008/LCU時間、Public IPv4 $0.005/時間/個、RDS db.t4g.micro $0.025/時間+gp3ストレージ$0.138/GB月、S3 $0.025/GB月、CloudWatch Logs $0.70/GB（取り込み）+$0.03/GB月（保存）、Route 53 $0.50/月+$0.40/100万クエリ、Amazon Translate $15.00/100万文字（リージョン差なし）、SES $0.10/1,000件（従量課金、プラン未加入）。

| 項目 | 月額目安 | 備考 |
|---|---|---|
| ALB（基本料金$18.40+LCU概算$5.84） | 〜$24.24 | LCUは実使用量に応じた課金のため、低トラフィックのソフトローンチ直後はこれより低くなり得る保守的な想定 |
| Public IPv4（ALB×2 AZ＋Fargate Task×1＝3個） | 〜$10.95 | ALBは有効なAZごとに1つのPublic IPv4を持つため、2 AZ構成で2個＋Task分1個。旧概算では他の少額項目に紛れて過小評価されていた |
| RDS db.t4g.micro（Single-AZ、MySQL、ストレージ20GB想定） | 〜$21.01 | **T4g/T3は無料CPUクレジットのベースラインを超えると追加のCPUクレジット課金が発生**するため、継続的に高負荷が続く場合は想定より増額し得る |
| S3・ECR・Route 53・CloudWatch Logs・データ転送 | 〜$2.33 | いずれも少額（低利用想定） |
| Amazon Translate・SES | 〜$1.55〜$7.50 | 低トラフィックのソフトローンチ初期の想定。Translateの文字数上限・レート制限はA-2で実装済み |
| NAT Gateway | $0 | **不採用のため課金なし**（採用した場合は基本料金だけで月額約$32.40、データ処理料金が別途加算されるため、Stage 1では明確に不採用とする根拠とする） |

| Fargateのサイズ | Fargate月額目安 | 上記合計との総計 | 判定 |
|---|---|---|---|
| **0.5 vCPU / 2GB（初期構築はこちらを採用、4.3.1節）** | 〜$26.53 | **約$86.61〜92.56/月** | 通常目標$90・上限$100の両方に収まる |
| 0.5 vCPU / 1GB（メモリ余裕なし） | 〜$22.49 | 約$82.57〜88.52/月 | 収まるが、PHP-FPM・Worker・画像処理の同時実行でメモリ不足のリスクが高い |
| 1 vCPU / 2GB（CPU高止まり時のみ変更） | 〜$44.98 | **約$105.06〜110.01/月** | **上限$100/月を超える**ため初期構築では採用しない |

**上振れ要因**（Pricing Calculatorの見積もりには現れない、実運用で発生し得るもの）: RDSのCPUクレジット超過、CloudWatch Logsのログ量増加、データ転送量の増加、ECSローリングデプロイ中に新旧Taskが一時的に2つ同時稼働する時間帯。これらを踏まえ、通常目標は$90/月以内、上限$100/月とし、AWS Budgetsのしきい値を4.5節のとおり調整します。

### 4.4.1 AWS Pricing Calculatorによる確認（完了）

4.6節が必須ゲートとするcalculator.awsでの共有見積もりを、0.5 vCPU/2GB構成（4.3.1節）で作成し、確認を完了しました。

- **見積日**: 2026-10-14
- **リージョン**: Singapore（ap-southeast-1）
- **Pricing Calculator生値**: $85.95/月
- **CloudWatch補正後**: 約$87.55/月（CalculatorのUI入力制約により、ログ取り込みを含むCloudWatchの費用を手動で$2/月へ補正）
- **SES新料金との差額も考慮した値**: 約$87.61/月
- **計画上の通常見込み**: 約$88/月
- **安全側の運用目標**: $90/月以内
- **上限**: $100/月
- **含まないもの**: 税金、ドメイン登録・更新料
- **変動費の扱い**: データ転送量・Amazon Translateの文字数・SESの送信数等、利用量に応じて増減する費用は、見積もり時点の低利用想定のまま固定せず、実請求（AWS Budgets、4.5節）で継続的に監視する

**Price List API再計算（4.4節本文）との整合**: Fargate（$26.53）・ALB（$24.24）・Public IPv4（$10.95）・RDS（$21.01）という主要な固定費4項目は、Price List API再計算とPricing Calculatorの実測値が完全に一致しました。唯一の差分は、S3・ECR・Route 53・CloudWatch Logsの小計（Price List API再計算: 約$2.33／Pricing Calculator: 約$1.62）で、これは主にCloudWatchログ取り込みの手動補正の仕方の違いと、周辺少額費用の予備枠の取り方の違いによるものであり、構成選定を見直す必要のある乖離ではありません。

**取り扱い注意**: Pricing Calculatorのエクスポート（CSV）にはAWSアカウントIDが含まれるため、CSVファイル自体はGit管理しません（このRELEASE_PLAN.mdには集計値のみ記載）。

### 4.5 AWS Budgets通知設定

| しきい値 | 意味 |
|---|---|
| $50 | 早期確認（想定の半分を超えた時点で傾向を把握） |
| $90 | 通常目標超過（原因調査を開始） |
| $100 | 即時調査（上限到達、緊急対応） |

AWS Budgetsは支出を自動停止する仕組みではなく通知のみである点は[BLUEPRINT.md](BLUEPRINT.md#amazon-translateの費用制御)に明記済みの前提を踏襲します。

### 4.6 フェーズBの作業項目

1. ~~AWS Pricing Calculator（calculator.aws）で0.5vCPU/2GB構成の共有見積もりを作成し、4.4節のPrice List API再計算と大きく乖離しないことを確認する~~ **完了（2026-10-14、4.4.1節）**。計画上の通常見込み約$88/月・安全側の運用目標$90/月以内・上限$100/月。実リソース作成・Terraform適用前に必要な確認は満たされました
2. AWS Budgets通知の設定（4.5）
3. インフラはTerraform等のIaCで管理する（手動コンソール操作に頼らず、7節の一時検証環境を確実に作成・削除できるようにするため）。state管理方式の確定内容は4.7節を参照
4. VPC・サブネットの作成。**AZ要件に注意**:
   - ALBには異なるAZのpublic subnetが最低2つ必要
   - RDS DB Subnet GroupにはSingle-AZ配置であっても、異なるAZのsubnetを最低2つ登録する必要がある（「Single-AZだからsubnetも1つ」ではない）
   - 推奨構成: **2 public subnet + 2 private subnet**
5. Security Groupの作成（ALB→Fargate Task、Fargate Task→RDSのみを許可）
6. IAMロールを用途別に分離して作成（最小権限）:
   - **Task Execution Role**: ECRからのイメージpull、CloudWatch Logsへの出力、起動時のParameter Storeからのsecret取得
   - **Task Role**: 実行中のアプリケーションコードが使うS3・Amazon Translateへのアクセス
7. ECRリポジトリの作成（lifecycle policyで世代数を制限し、無期限蓄積を防ぐ。ただし直近の安定世代へすぐ切り戻せる本数は残す、8節参照）
8. RDS for MySQL（Single-AZ、private subnet、4で作成したDB Subnet Group使用）の作成
9. S3バケット（private）の作成。lifecycle policyは対象を限定して設定する（依頼写真に単純な日数ベースの保持期間を設定すると、利用中の写真が自動削除される恐れがあるため）:
   - 依頼に紐づく**利用中の写真**: 日数では削除しない。`request_photos`のDB行削除に連動してアプリケーション側がオブジェクトを削除する運用のみとし、S3 lifecycleでの自動削除対象には含めない
   - **incomplete multipart upload**: 一定日数後にlifecycle policyで自動削除
   - バージョニングを使う場合の**noncurrent version**: 保持期間を決めてlifecycle policyで削除
   - **一時ファイル**: 専用prefix（例: `tmp/`）へ分離し、そのprefixのみ期限削除の対象とする
10. Parameter Store（Standard）への秘密情報登録（BACKLOG P0-5）。DBパスワード・`APP_KEY`等はSecureStringとして保存する
11. ACM証明書の発行とDNS検証（Route 53でのCNAMEレコード追加）
12. ALBの作成（public subnet、ACM証明書をHTTPSリスナーへ紐付け）
13. Route 53 Hosted Zoneとドメインの紐付け
14. CloudWatch Logsのロググループにretention期間を設定（無期限保存を避ける）

### 4.7 Terraform state管理設計（確定）

#### 4.7.1 ディレクトリ構成

```
infra/terraform/
  modules/
    network/      # VPC, 2 public + 2 private subnet, IGW, route table
    security/     # Security Group（ALB→Task、Task→RDS）
    iam/          # Task Execution Role, Task Role
    ecr/
    rds/
    s3/
    alb/
    ecs/          # Cluster, Task Definition, Service
    dns/          # Route 53, ACM
    monitoring/   # CloudWatch Logs, Alarms, Budgets
  environments/
    shared/              # production/stagingが共有するリソース（現時点ではECRのみ）
      backend.tf           # state key = "shared/terraform.tfstate"
      main.tf
      variables.tf
      terraform.tfvars
    production/
      backend.tf           # state key = "production/terraform.tfstate"
      main.tf              # モジュール呼び出し。ECRは `data "aws_ecr_repository"` で
      variables.tf          # 名前参照のみ（sharedのstateを直接読み取らない）
      terraform.tfvars      # 機密値を含まない設定のみ、Git管理
    staging/
      backend.tf             # state key = "staging/terraform.tfstate"
      main.tf                # 同じmodulesを再利用、7節の一時検証用
      variables.tf
      terraform.tfvars
```

production/stagingはTerraform workspaceで切り替えるのではなく、**ディレクトリと`backend.tf`自体を分離**します。workspaceはバックエンド設定を共有したまま状態ファイルだけ切り替える仕組みのため、誤ったworkspaceに対してapplyしてしまう事故のリスクがあります。

**`shared`を追加した理由**: ECR（コンテナイメージ）はproduction/staging間で同一アーティファクトを共有する設計（同じcommit SHAのイメージをstagingで検証後、同じイメージをproductionへ promote）のため、production/stagingそれぞれのstateから同じECRリポジトリを作成・所有しようとすると競合します。`shared`で一度だけ作成し、production/stagingは`terraform_remote_state`（state全体の読み取り権限が必要になる）ではなく、リポジトリ名を指定した`data "aws_ecr_repository"`参照のみで利用します。

**apply順序**: 依存関係上、**`shared`→`production`／`staging`の順**でなければなりません（`shared`のECRが存在しない状態で`production`/`staging`の`plan`を実行すると、`data "aws_ecr_repository"`の参照が失敗します。これは想定どおりの挙動です）。`destroy`はこの逆順（`production`/`staging`→`shared`）で行います。

#### 4.7.2 Stateバックエンド

```hcl
# environments/production/backend.tf
terraform {
  required_version = ">= 1.10.0, < 2.0.0"

  backend "s3" {
    bucket       = "dananghelp-terraform-state-<一意なsuffix>"
    key          = "production/terraform.tfstate"
    region       = "ap-southeast-1"
    encrypt      = true
    use_lockfile = true   # Terraform 1.10+のネイティブS3ロック（DynamoDB不要、今後段階的に廃止予定とされる旧方式を新規採用しない）
  }
}
# environments/staging/backend.tf は bucket は同一、key = "staging/terraform.tfstate" のみ変更
# environments/shared/backend.tf は bucket は同一、key = "shared/terraform.tfstate" のみ変更
```

- **バージョン制約**: `>= 1.10.0`だけでは将来のTerraform 2.x系も許容してしまうため、`< 2.0.0`の上限を明記する
- **バケット名**: `dananghelp-terraform-state`はS3全体でグローバルに一意である必要があり、実際の名前は着手時にAWS CLIで空き確認した一意なsuffixを付けて確定する（AWSアカウントIDは秘密ではないがリポジトリへ積極的に載せる必要もないため、ランダムなsuffixを推奨）
- **ロック**: Terraform 1.10以降のS3ネイティブロック（`use_lockfile = true`、stateと同じバケットに`.tflock`ファイルを作成）を採用。DynamoDBテーブルは追加しない

#### 4.7.3 Stateバケットのセキュリティ設定（bootstrapスクリプトで作成）

Terraform管理対象外（循環を避けるため）とし、一度だけ再実行可能なbootstrapスクリプトで作成する。スクリプトには以下をすべて含める:

- バケット作成（`ap-southeast-1`）
- バージョニング有効化
- デフォルト暗号化（SSE-S3）
- パブリックアクセスブロック（4項目すべて`true`）
- **HTTPS強制のバケットポリシー**（`aws:SecureTransport=false`の場合に明示的に`Deny`。SSE-S3・バージョニング・パブリックアクセスブロックだけでは不十分で、AWS公式も推奨する設定）
- 非現行バージョン（noncurrent version）の保持期間を設定するlifecycle policy（即時削除ではなく、例えば365日後に削除）
- 作成後の検証コマンド（バージョニング・暗号化・パブリックアクセスブロック・バケットポリシーが実際に反映されたことを`aws s3api get-*`系コマンドで確認）
- スクリプト内コメントとして「このバケットは削除禁止・用途（Terraform state保存専用）」を明記

Object LockやMFA Deleteは、現在の小規模構成では運用の複雑化に見合わないため採用しません。

#### 4.7.4 IAM権限の分離

**Terraform実行ロール**（ECSのTask Execution Role/Task Roleとは別物。ローカル実行・CI実行の両方で使うロール）に必要な権限を、対象ごとに最小化します。

| 対象 | 必要な権限 | 備考 |
|---|---|---|
| バケット自体 | `s3:ListBucket` | バケットレベル |
| state本体（`<env>/terraform.tfstate`） | `s3:GetObject`、`s3:PutObject` | `s3:DeleteObject`は通常不要 |
| lockfile（`<env>/terraform.tfstate.tflock`） | `s3:GetObject`、`s3:PutObject`、`s3:DeleteObject` | ロック解放時に削除が必要なため、state本体と権限セットが異なる |

production用CIロールは`production/*`パスのみ、staging用CIロールは`staging/*`パスのみに制限し、相互にアクセスできないようにします。

**認証情報の扱い**: `backend.tf`に`profile`や`access_key`/`secret_key`を直接書きません。ローカル実行では`AWS_PROFILE`環境変数（例: `danang-help-admin`、A-2で既に使用したIAM Identity Centerプロファイル）、CI実行ではGitHub Actions OIDCによる一時クレデンシャル（長期アクセスキーを持たない、4.6節・RELEASE_PLAN全体の既存方針と一致）を使用します。

#### 4.7.5 秘密情報の境界（表現の訂正）

旧版の「stateに秘密値が一切含まれない」という表現は不正確です。正確には以下のとおりです。

> **`DB_PASSWORD`・`APP_KEY`等のアプリケーション秘密値をTerraformへ渡さず、stateへ保存しない。**

Terraform state自体には、RDSのエンドポイント・各リソースのARN・IAM情報など、公開すべきでないインフラ情報が残ります。そのため秘密値を含めない設計であっても、**state自体を機密性のあるデータとして扱い、4.7.2〜4.7.4の暗号化・アクセス制限を厳格に維持する**ことが必要です（Terraform公式もstateを機密データとして扱うことを推奨）。

具体的な境界:
- `SecureString`パラメータ（`DB_PASSWORD`・`APP_KEY`等）は、**値だけでなくパラメータリソース自体もTerraformで作成しません**。IAMポリシー側はパス単位のワイルドカード（例: `arn:aws:ssm:ap-southeast-1:<account-id>:parameter/dananghelp/production/*`）で権限を付与するため、Terraformが個々の`SecureString`パラメータの存在を知る必要がありません
- 実際のパラメータ作成・値投入は、D-3と同様に管理者が手動で行う運用手順として別途文書化します。**値はCLIの引数へ直書きしない**（シェル履歴・プロセス一覧に残るため、`--cli-input-json`や標準入力経由の投入方法を手順化する）
- カスタマー管理KMSキーを使う場合、Task Execution RoleのIAMポリシーに`kms:Decrypt`が必要なことに加え、**KMSキーポリシー側でも同Roleへの許可が別途必要**（AWS管理キー`alias/aws/ssm`使用時はIAMポリシーのみで足りる）
- SSMパラメータをコンテナ起動時の環境変数へ注入する権限（ECSタスク定義の`secrets`フィールド解決）は、実行中のアプリケーションコードが使う**Task Roleではなく、起動時処理を行うTask Execution Role側**へ付与する（4.6節項目6の既存区分と一致）
- 非機密な設定値（`APP_URL`等の`String`タイプパラメータ）はTerraformで管理してよい

#### 4.7.6 Git管理

```
# .gitignore へ追加
.terraform/
*.tfstate*
*.tfplan
crash.log
*.auto.tfvars
*.tfvars.json
override.tf
*_override.tf
```

- `terraform.tfvars`（非機密値のみ）は例外的にGit管理する
- `.terraform.lock.hcl`（プロバイダのバージョン固定）はGit管理する

## 5. フェーズC — デプロイ機構

フェーズBのインフラを前提に、実際にアプリケーションをデプロイできる状態にします。

| 項目 | 内容 | 関連BACKLOG |
|---|---|---|
| C-1 本番Dockerイメージの確立 | 4.3節のマルチステージビルド（Vite資産ビルド→Nginx・PHP-FPM双方へ同一成果物をコピー→軽量ランタイム）を`docker/php/Dockerfile`・`docker/nginx/default.conf`へ実装。両イメージとも同一Git commit SHAタグでpush | — |
| C-2 CI/CD | push/PR時にPHPUnit・Vitest・`tsc --noEmit`・lintを自動実行。mainマージ時のデプロイパイプライン全体の実行順序は下記5.1 | [BACKLOG P0-4](BACKLOG.md#p0-4-cicd) |
| C-3 DBマイグレーション手順 | デプロイパイプラインの一部として実行する（migrationの位置づけは下記5.1のステップ5〜6）。one-off task・失敗時の扱い・同時実行防止・Seederとの分離の詳細も5.1に記載 | — |
| C-4 Worker/Scheduler常駐設定 | 同一Task内でqueue:work・schedule:workを独立プロセスとして起動し、異常終了時のTask全体再起動を許容する運用（[BLUEPRINT.md](BLUEPRINT.md#ecs構成-単一task内の4コンテナv031で変更)方針どおり） | [BACKLOG P0-6](BACKLOG.md#p0-6-本番キューworkerとschedulerの常駐設定) |
| C-5 Storage署名付きURL・CORS | S3バケットのCORS設定、`temporaryUrl()`が本番でも正しく機能することを確認 | [BACKLOG P0-7](BACKLOG.md#p0-7-storageの公開署名付きurlcors設定) |
| C-6 メール送信ドメイン認証 | SES設定、SPF/DKIM/DMARC、Mailpitからの切替 | [BACKLOG P0-8](BACKLOG.md#p0-8-メール送信ドメインの認証) |
| C-7 queue_jobsテーブル名の衝突について | [BLUEPRINT.md](BLUEPRINT.md#18-aws構成案)の既存方針どおり、キューはLaravel既定の`jobs`テーブルのまま、ドメイン側は既に`service_jobs`へ改名済み（追加対応不要） | — |

### 5.1 デプロイパイプラインの実行順序（C-2・C-3）

**「Task Definitionの更新」と「トラフィック切り替え」を明確に分離する**ため、mainマージ時のパイプラインは以下の順序で実行します。

1. テスト（PHPUnit・Vitest・`tsc --noEmit`・lint）
2. Dockerビルド（4.3節のマルチステージビルド。Nginx・PHP-FPM双方のイメージを同一のフロントエンド資産から作成）
3. **`sha-<full-commit-sha>`タグ**でECRへpush（`latest`タグは作成しない。Terraform第2段階のECR lifecycle policyがsha-タグのみを対象にタグ付き画像の世代管理を行うため。8節参照）
4. 新しいECS Task Definitionを登録する（**この時点ではまだ稼働中のServiceへは反映しない**）
5. 4で登録した新Task Definitionを使って、**migration one-off task**（`aws ecs run-task`、常駐Serviceとは別の一時起動）として`php artisan migrate --force`を実行する
6. migrationの成功を待機する。**失敗した場合はここでパイプラインを中断し、以降のステップへ進まない**（Task Definitionは4で登録済みだが、ECS Serviceは古いTask Definitionのまま稼働を継続している — 「登録」と「切り替え」を分けているため、失敗時に何も壊れていない）
7. migrationが成功した場合のみ、5と同じ新Task Definitionを使って一時Taskを起動し、`php artisan legal:check`（A-3、`App\Support\Legal\LegalConfigurationValidator`）を実行する。運営者名・問い合わせ先・利用規約/プライバシーポリシーのバージョン・発効日がプレースホルダー/draftのままであれば、ここでパイプラインを中断する（トラフィック切り替え前なので、旧Serviceは影響を受けない）
8. legal:checkが成功した場合のみ、ECS Serviceを新Task Definitionへ更新する（ここで初めて新しいコンテナが起動し、トラフィック切り替えが始まる）
9. ALBのヘルスチェック成功（新Taskが正常応答すること）を確認する
10. 9で失敗した場合は、ECSのデプロイ設定（最小健全率・最大率）に従って**旧Service（旧Task Definition）を維持**し、新Taskへは切り替えない

ステップ4・7・8の関係を明確にすると：**新Task Definition登録（4）→ そのTask DefinitionによるECS one-off taskでlegal:check実行（7、migration one-off taskの直後）→ 成功した場合のみECS Serviceを更新（8）→ ALBヘルスチェック（9）後にトラフィックが移行**という順序です。legal:checkは常駐Serviceの新Taskが起動した後ではなく、Service更新（8）より前の、独立した一時Task（migrationと同様の`aws ecs run-task`）として実行します。

**同時実行防止**: 上記パイプライン自体をCI側でジョブ直列化し、2つのデプロイが同時に走らないようにする（Laravel標準の`migrate`コマンド自体に排他制御はないため、パイプライン側で担保する）。

**SeederとMigrationの分離**: 本番パイプラインが自動実行するのはステップ5の`migrate --force`のみ。Seeder（`CategorySeeder`等）は別のone-off taskとして、必要な時だけ手動トリガーで実行する（D-4「本番SeederでDemoデータを作成しないことの確認」と合わせて、自動実行対象から明確に分離する）。

**後方互換性の前提**: ステップ5〜7の間、旧Task（旧コード）と新しく適用されたDBスキーマが一瞬共存するため、マイグレーションは後方互換性のある変更であることを前提とします（詳細は8節のexpand/contract方針を参照）。

## 6. フェーズD — 運用確認

フェーズCまでで実際に動いているインフラ上で検証する項目です。

| 項目 | 内容 | 関連BACKLOG |
|---|---|---|
| D-1 DBバックアップ・復元テスト | RDS自動バックアップの有効化、実際のスナップショット復元を1回検証 | [BACKLOG P0-9](BACKLOG.md#p0-9-データベースバックアップと復元テスト) |
| D-2 エラー監視・ヘルスチェック・アラート | CloudWatchへの致命的エラー集約。ヘルスチェックは**`/health`（軽量な生存確認、プロセスが応答するかのみ）とDB接続確認を含む別経路（深いヘルスチェック）を分ける**。ALBのヘルスチェックには前者（`/health`）を使い、DB障害時にALBがTask自体を不健全と誤判定して無限に再起動を繰り返す事態を避ける | [BACKLOG P0-10](BACKLOG.md#p0-10-エラー監視ヘルスチェックアラート) |
| D-3 Admin初期作成手順 | パスワードをリポジトリ・ログへ残さない本番Admin作成手順の文書化・実施 | [BACKLOG P0-12](BACKLOG.md#p0-12-管理者アカウントの安全な初期作成手順) |
| D-4 本番Seeder確認 | 本番デプロイ時に`CategorySeeder`等の必須マスタのみ実行され、Demo Provider等が作成されないことを確認 | [BACKLOG P0-13](BACKLOG.md#p0-13-本番seederでdemoデータを作成しないことの確認) |
| D-5 README・運用手順 | フェーズA〜Dすべてが固まった後に、正確な内容で作成 | [BACKLOG P0-14](BACKLOG.md#p0-14-readme運用手順) |

## 7. ステージング公開と最終確認チェックリスト

常設のステージング環境は作らず、**このフェーズの検証時だけ一時的に本番相当環境を構築**し、検証完了後は削除します（4.1節の確定事項）。

確認項目：

- [ ] 実際のAmazon Translateで翻訳が機能する（FakeTranslatorではない）
- [ ] 実際のSES経由でメールが届く（Mailpitではない）
- [ ] S3への写真アップロード・署名付きURLでの表示が機能する
- [ ] Queue Worker・Schedulerが本番相当環境で正しく常駐・自動確定バッチが動作する
- [ ] Customer／Provider／Adminの権限制御が本番相当環境でも機能する（モデレーション非表示・private fields等）
- [ ] モバイル表示（PC/モバイル双方）でレイアウト崩れ・Consoleエラーがない
- [ ] 書き込み系エンドポイントのレート制限が機能する
- [ ] 本番ドメインでのTLS証明書（ACM）が有効
- [ ] AWS Budgetsのアラートが実際に通知される設定になっている
- [ ] 一時構築した検証環境を確認後に削除し、課金が残らないことを確認

## 8. ロールバック手順

本番の`migrate:rollback`を基本手段にはしません。`down()`の実行でデータが失われる可能性があり、障害対応中に追加のデータ損失を招くリスクがあるためです。

### 8.1 基本方針

1. **アプリケーションのロールバック（第一手段）**: ECSのTask定義を**直前のGit commit SHAタグ付きイメージ**へ戻す。C-2により各ビルドが`sha-<full-commit-sha>`の不変タグでECRへpushされているため（`latest`タグは作成しない）、特定のコミット単位で確実に切り戻せる
2. **DBスキーマ変更は原則として後方互換マイグレーション＋forward fixで対応する**: 新しいコードが前のDBスキーマでも、前のコードが新しいDBスキーマでも、どちらでも動作する状態を維持しながら進める（expand/contract方式）。問題が見つかった場合は`down()`で巻き戻すのではなく、**前進方向の修正（forward fix）を新しいマイグレーション・新しいデプロイとして適用する**
3. **破壊的なスキーマ変更（カラム削除・型変更等）は、expand/contract方式で複数リリースに分割する**: 例）(1)新カラムを追加しつつ旧カラムも残す→(2)アプリケーションを新カラム参照へ切り替える→(3)旧カラムが不要になったことを確認した別リリースで削除する、という順序を踏み、1回のデプロイで破壊的変更とアプリ切り替えを同時に行わない
4. **`migrate:rollback`は、内容を個別に確認した場合に限り、最終手段の一つとして使用可**（実行前に必ずバックアップ（D-1）を取得する）
5. **スナップショット復元は重大障害時の最終手段**とし、直近のRDS自動バックアップ（D-1で検証済み）からの復元を行う。これは他のすべての手段で対応できない場合にのみ用いる

### 8.2 手順

Stage 1は単一環境構成（常設のステージング・旧環境は存在しない）のため、「Route 53を旧環境へ向ける」という切り戻し手段は**実行不能**です。以下の現実的な手段を使います。

1. **第一手段**: ECS Serviceを**旧Task Definition**（直前の安定リビジョン）へ切り戻す（8.1-1と同じ、これが基本）
2. **サービス停止が必要な場合**（例: データ破損が疑われ、即座に書き込みを止める必要がある）: ALBリスナールールの**固定レスポンス（fixed response）**を返す設定に切り替えるか、事前に用意したメンテナンス表示用の軽量Taskへルーティング先を切り替える
3. **DNSレベルの切り替え**: 別環境（他のリージョン・他のスタック等）が実在する場合にのみ使う補助的な手段であり、Stage 1の標準手順には含めない
4. 上記の優先順位（アプリロールバック→forward fix→個別確認の上でのrollback→スナップショット復元、8.1節）に従って対応する
5. ロールバック実施後は、原因調査が完了し再発防止策が講じられるまで再デプロイしない

## 9. v0.1.1タグ付けとソフトローンチ移行基準

フェーズA〜Dおよび7節のチェックリストがすべて完了した時点で、以下の順序で進めます（v0.1.0タグ作成時と同様の手順）。

1. 本ドキュメント・BACKLOG.mdの更新（完了した項目の反映）
2. ドキュメント更新のレビュー・コミット・push
3. 7節の最終確認チェックリストを実施
4. working treeがclean、HEADがorigin/mainと一致していることを確認
5. `git tag -a v0.1.1 -m "<内容を要約したメッセージ>"`で注釈付きタグを作成
6. タグのpushは別途明示的な確認を得てから実行

ソフトローンチ移行（実際のユーザーへの告知・限定エリア/カテゴリでの受付開始）は、本ドキュメントのスコープ外とし、別途マーケティング・運営側の判断で決定します。
