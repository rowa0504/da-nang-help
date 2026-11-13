terraform {
  # 1.11.0 is the floor because it is the first release with
  # write-only attributes (password_wo/value_wo), used by
  # modules/rds for the RDS master password (3B). See
  # infra/terraform/README.md for the pinned verification container
  # version (currently 1.17.1) — this constraint is the compatibility
  # range, not the exact version run.
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
    random = {
      source = "hashicorp/random"
      # 3.7.0 is the floor because it is the first release with the
      # ephemeral "random_password" resource, used by modules/rds (3B).
      version = ">= 3.7.0, < 4.0.0"
    }
  }
}
