terraform {
  required_version = ">= 1.10.0, < 2.0.0"

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
