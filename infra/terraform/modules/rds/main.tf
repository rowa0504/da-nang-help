# Master credentials use a Terraform 1.11+ write-only attribute
# (password_wo on aws_db_instance, value_wo on the SSM SecureString
# parameter) combined with an ephemeral random_password resource, so
# the generated password is never persisted to plan, state, output,
# or logs (see docs/RELEASE_PLAN.md 3B). password_wo_version and
# value_wo_version both reference the same var.db_password_version —
# bumping it in one apply resends the identical freshly-generated
# password to both RDS and SSM in that single run (see the partial-
# failure recovery procedure in infra/terraform/README.md).
#
# The master user is NOT the application's runtime DB user. Laravel's
# normal ECS Service must never receive these credentials. A separate,
# minimal-privilege app DB user and its own SSM parameters are created
# in 3D, read only by the normal ECS Service's Task Execution Role. A
# temporary, one-off DB-initialization Task (its own Execution Role)
# is the only thing that may read DB_MASTER_USERNAME /
# DB_MASTER_PASSWORD — see the "IAM boundary" gate recorded in
# infra/terraform/README.md: no production ECS Service may be started
# before that separation is complete.

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
    }
    random = {
      source = "hashicorp/random"
    }
  }
}

ephemeral "random_password" "db_master_password" {
  length           = 28
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

resource "aws_db_instance" "this" {
  identifier = "${var.project}-${var.environment}-mysql"

  engine                   = "mysql"
  engine_version           = "8.4.11"
  engine_lifecycle_support = "open-source-rds-extended-support-disabled"
  instance_class           = "db.t4g.micro"
  allocated_storage        = var.allocated_storage
  max_allocated_storage    = 0
  storage_type             = "gp3"
  storage_encrypted        = true
  multi_az                 = false
  publicly_accessible      = false
  port                     = 3306
  db_subnet_group_name     = var.db_subnet_group_name
  vpc_security_group_ids   = [var.rds_security_group_id]

  db_name  = var.db_name
  username = var.master_username

  # Write-only: never a plain `password`. See module header comment.
  password_wo         = ephemeral.random_password.db_master_password.result
  password_wo_version = var.db_password_version

  auto_minor_version_upgrade = true
  apply_immediately          = false

  backup_retention_period = var.backup_retention_period
  backup_window           = var.backup_window
  maintenance_window      = var.maintenance_window

  deletion_protection       = var.deletion_protection
  skip_final_snapshot       = false
  final_snapshot_identifier = var.final_snapshot_identifier
  copy_tags_to_snapshot     = true

  performance_insights_enabled        = false
  monitoring_interval                 = 0
  iam_database_authentication_enabled = false

  tags = var.tags
}

resource "aws_ssm_parameter" "db_host" {
  name  = "/dananghelp/${var.environment}/DB_HOST"
  type  = "String"
  value = aws_db_instance.this.address
  tags  = var.tags
}

resource "aws_ssm_parameter" "db_port" {
  name  = "/dananghelp/${var.environment}/DB_PORT"
  type  = "String"
  value = tostring(aws_db_instance.this.port)
  tags  = var.tags
}

resource "aws_ssm_parameter" "db_database" {
  name  = "/dananghelp/${var.environment}/DB_DATABASE"
  type  = "String"
  value = var.db_name
  tags  = var.tags
}

resource "aws_ssm_parameter" "db_master_username" {
  name  = "/dananghelp/${var.environment}/DB_MASTER_USERNAME"
  type  = "String"
  value = var.master_username
  tags  = var.tags
}

# Write-only: never a plain `value`. See module header comment.
resource "aws_ssm_parameter" "db_master_password" {
  name = "/dananghelp/${var.environment}/DB_MASTER_PASSWORD"
  type = "SecureString"

  value_wo         = ephemeral.random_password.db_master_password.result
  value_wo_version = var.db_password_version

  tags = var.tags
}
