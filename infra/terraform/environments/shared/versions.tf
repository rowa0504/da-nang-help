terraform {
  # Kept in sync with environments/production and environments/staging
  # (1.11.0+ is required there for write-only attributes used by
  # modules/rds) so all 3 environments share one Terraform CLI
  # baseline. shared itself does not use write-only attributes.
  required_version = ">= 1.11.0, < 2.0.0"

  required_providers {
    aws = {
      source = "hashicorp/aws"
      # 6.68.0 is kept as the floor — it was the latest published
      # version as of 2026-10-14, when this constraint was set; the
      # floor is not bumped on every subsequent release. `terraform init
      # -backend=false` has since been run, resolving 6.69.0 (within
      # this constraint), pinned in the committed .terraform.lock.hcl.
      # See infra/terraform/README.md.
      version = ">= 6.68.0, < 7.0.0"
    }
  }
}
