terraform {
  required_version = ">= 1.10.0, < 2.0.0"

  required_providers {
    aws = {
      source = "hashicorp/aws"
      # Floor is the latest published version as of 2026-10-14
      # (confirmed against the provider's GitHub releases). No
      # `terraform init` has been run against this configuration yet, so
      # there is no .terraform.lock.hcl — once init runs, pin the exact
      # resolved version there. See infra/terraform/README.md.
      version = ">= 6.68.0, < 7.0.0"
    }
  }
}
