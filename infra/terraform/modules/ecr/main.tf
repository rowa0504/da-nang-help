# Shared across production and staging (see environments/shared) — the
# same commit-SHA-tagged image is promoted from staging to production,
# never rebuilt per environment. Tags are always "sha-<full-commit-sha>"
# (see docs/RELEASE_PLAN.md 5.1); a "latest" tag is never pushed, so the
# lifecycle policy below only ever needs to reason about sha-* tags and
# untagged images — it never risks expiring a "latest" tag because one
# never exists.

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
    }
  }
}

resource "aws_ecr_repository" "this" {
  name                 = var.repository_name
  image_tag_mutability = "IMMUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }

  encryption_configuration {
    encryption_type = "AES256"
  }

  # Never force-delete a repository that still has images in it — this
  # repository is shared infrastructure referenced by both environments,
  # not a throwaway.
  force_delete = false

  tags = var.tags
}

resource "aws_ecr_lifecycle_policy" "this" {
  repository = aws_ecr_repository.this.name

  policy = jsonencode({
    rules = [
      {
        rulePriority = 1
        description  = "Expire untagged images after ${var.lifecycle_untagged_expire_days} days"
        selection = {
          tagStatus   = "untagged"
          countType   = "sinceImagePushed"
          countUnit   = "days"
          countNumber = var.lifecycle_untagged_expire_days
        }
        action = {
          type = "expire"
        }
      },
      {
        rulePriority = 2
        description  = "Keep only the most recent ${var.lifecycle_keep_tagged_count} sha-tagged images"
        selection = {
          tagStatus     = "tagged"
          tagPrefixList = ["sha-"]
          countType     = "imageCountMoreThan"
          countNumber   = var.lifecycle_keep_tagged_count
        }
        action = {
          type = "expire"
        }
      }
    ]
  })
}
