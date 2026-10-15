# Two separate roles, by design (RELEASE_PLAN.md 4.6 item 6):
#   - task_execution: startup-time operations only (image pull, the
#     agent's own log stream, resolving SSM-sourced secrets into
#     container env vars). Used by the ECS agent, never by application
#     code.
#   - task: used by the running application code itself (Amazon
#     Translate calls from the Queue Worker, S3 photo storage from
#     PHP-FPM). Never used for startup-time operations.
#
# iam:PassRole for whoever registers the Task Definition / updates the
# ECS Service (today: the Terraform execution identity; later: a Phase C
# CI deploy role) is deliberately NOT granted by this module — see
# docs/RELEASE_PLAN.md, which defers that to Phase C's CI deploy role
# design, where it will be scoped to exactly these two role ARNs with an
# iam:PassedToService=ecs-tasks.amazonaws.com condition.
#
# A GitHub Actions OIDC provider/role is also deliberately out of scope
# here — deferred to Phase C alongside the actual CI/CD workflow it
# would be used by.

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
    }
  }
}

data "aws_partition" "current" {}
data "aws_region" "current" {}
data "aws_caller_identity" "current" {}

locals {
  name_prefix = "${var.project}-${var.environment}"

  # Built from provider-supplied data sources, never a hardcoded
  # account ID or region string, so no real AWS account ID ever
  # appears in committed code.
  ssm_parameter_arn_pattern = "arn:${data.aws_partition.current.partition}:ssm:${data.aws_region.current.region}:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_path_prefix}/*"
}

# ---- Task Execution Role ----

data "aws_iam_policy_document" "task_execution_assume_role" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "task_execution" {
  name               = "${local.name_prefix}-ecs-task-execution"
  assume_role_policy = data.aws_iam_policy_document.task_execution_assume_role.json

  tags = var.tags
}

data "aws_iam_policy_document" "task_execution" {
  statement {
    sid       = "EcrAuth"
    actions   = ["ecr:GetAuthorizationToken"]
    resources = ["*"] # This action does not support resource-level permissions.
  }

  statement {
    sid = "EcrPull"
    actions = [
      "ecr:BatchGetImage",
      "ecr:GetDownloadUrlForLayer",
      "ecr:BatchCheckLayerAvailability",
    ]
    resources = [var.ecr_repository_arn]
  }

  statement {
    sid = "Logs"
    actions = [
      "logs:CreateLogStream",
      "logs:PutLogEvents",
    ]
    resources = var.log_group_stream_arns
  }

  statement {
    sid       = "SsmSecrets"
    actions   = ["ssm:GetParameters"]
    resources = [local.ssm_parameter_arn_pattern]
  }

  # Deliberately no kms:Decrypt statement: Parameter Store's default
  # AWS-managed key (alias/aws/ssm) is designed to permit decryption by
  # any IAM principal in the same account without a separate IAM grant
  # — see docs/RELEASE_PLAN.md 4.7.5. Add a scoped kms:Decrypt statement
  # here (naming the specific customer-managed key ARN, never a
  # wildcard) only if this project moves off the AWS-managed key.
}

resource "aws_iam_role_policy" "task_execution" {
  name   = "${local.name_prefix}-ecs-task-execution"
  role   = aws_iam_role.task_execution.id
  policy = data.aws_iam_policy_document.task_execution.json
}

# ---- Task Role ----

data "aws_iam_policy_document" "task_assume_role" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "task" {
  name               = "${local.name_prefix}-ecs-task"
  assume_role_policy = data.aws_iam_policy_document.task_assume_role.json

  tags = var.tags
}

data "aws_iam_policy_document" "task" {
  statement {
    sid       = "Translate"
    actions   = ["translate:TranslateText"]
    resources = ["*"] # Amazon Translate does not support resource-level permissions.
  }

  statement {
    sid = "PhotosBucket"
    actions = [
      "s3:PutObject",
      "s3:GetObject",
      "s3:DeleteObject",
    ]
    # Object-level only — s3:ListBucket is not required (the app never
    # lists/enumerates this bucket, only reads/writes specific known
    # keys; confirmed by reading app/Services/Photos/ProcessUploadedPhoto.php
    # and the Actions that call Storage::disk()->delete()).
    resources = ["${var.s3_bucket_arn}/*"]
  }
}

resource "aws_iam_role_policy" "task" {
  name   = "${local.name_prefix}-ecs-task"
  role   = aws_iam_role.task.id
  policy = data.aws_iam_policy_document.task.json
}
