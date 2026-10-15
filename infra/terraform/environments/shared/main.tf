# This environment owns the ECR repository exclusively. production and
# staging must NOT create or own this repository from their own state —
# they look it up by name via `data "aws_ecr_repository"` (see
# docs/RELEASE_PLAN.md 4.7.1 "sharedを追加した理由"). This keeps the
# same commit-SHA-tagged image promotable from staging to production
# without a rebuild, while avoiding cross-environment state-read access.

module "ecr" {
  source = "../../modules/ecr"

  repository_name                = var.ecr_repository_name
  lifecycle_keep_tagged_count    = var.ecr_lifecycle_keep_tagged_count
  lifecycle_untagged_expire_days = var.ecr_lifecycle_untagged_expire_days
}
