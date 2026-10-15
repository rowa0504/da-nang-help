# One CloudWatch Log Group per container in the Stage 1 Fargate Task
# (RELEASE_PLAN.md 4.3: Nginx, PHP-FPM, Queue Worker, Scheduler). Alarms
# are deliberately out of scope here — they belong in a separate,
# later monitoring module, not bundled into log group creation.
#
# Never log personal data, secrets, or machine-translation source/target
# text (see app/Jobs/TranslateServiceRequestJob.php and
# TranslateOfferJob.php, which already restrict what they log to
# locale/byte-count/error-code — this module does not change that
# application-level behavior, it only provisions where those logs land).

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
    }
  }
}

locals {
  name_prefix = "/ecs/${var.project}/${var.environment}"
  containers  = ["nginx", "php-fpm", "worker", "scheduler"]
}

resource "aws_cloudwatch_log_group" "this" {
  for_each = toset(local.containers)

  name              = "${local.name_prefix}/${each.key}"
  retention_in_days = var.retention_in_days

  tags = merge(var.tags, {
    Name = "${local.name_prefix}/${each.key}"
  })
}
