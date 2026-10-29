# ALB SG <-> ECS Task SG <-> RDS SG cross-references are expressed via
# separate rule resources (aws_vpc_security_group_ingress_rule /
# aws_vpc_security_group_egress_rule) so the three security groups can
# reference each other without a circular dependency between the
# aws_security_group resources themselves.
#
# No DNS (port 53) rule is created: queries to the VPC's Amazon-provided
# DNS Resolver (the base+2 address of each VPC) do not traverse normal
# Security Group rule evaluation, so no egress rule is needed for it.
#
# No egress rule is created on the RDS security group: the RDS instance
# itself does not initiate outbound connections in this design. If a
# future feature has the DB instance itself initiate outbound
# connections, confirm that feature's communication requirements and
# add only the egress rule(s) it actually needs at that time.

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
    }
  }
}

resource "aws_security_group" "alb" {
  name        = "${var.project}-${var.environment}-alb-sg"
  description = "ALB security group"
  vpc_id      = var.vpc_id
  tags        = var.tags
}

resource "aws_vpc_security_group_ingress_rule" "alb_http" {
  security_group_id = aws_security_group.alb.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "tcp"
  from_port         = 80
  to_port           = 80
  description       = "HTTP entry point; ALB listener redirects to HTTPS (configured in 3C)"
}

resource "aws_vpc_security_group_ingress_rule" "alb_https" {
  security_group_id = aws_security_group.alb.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "tcp"
  from_port         = 443
  to_port           = 443
  description       = "HTTPS entry point for application traffic"
}

resource "aws_security_group" "ecs_task" {
  name        = "${var.project}-${var.environment}-ecs-task-sg"
  description = "ECS task security group"
  vpc_id      = var.vpc_id
  tags        = var.tags
}

resource "aws_vpc_security_group_ingress_rule" "ecs_task_from_alb" {
  security_group_id            = aws_security_group.ecs_task.id
  referenced_security_group_id = aws_security_group.alb.id
  ip_protocol                  = "tcp"
  from_port                    = var.container_port
  to_port                      = var.container_port
  description                  = "Accept requests from the ALB only"
}

resource "aws_vpc_security_group_egress_rule" "alb_to_ecs_task" {
  security_group_id            = aws_security_group.alb.id
  referenced_security_group_id = aws_security_group.ecs_task.id
  ip_protocol                  = "tcp"
  from_port                    = var.container_port
  to_port                      = var.container_port
  description                  = "Forward requests to ECS tasks on the container port"
}

resource "aws_vpc_security_group_egress_rule" "ecs_task_https" {
  security_group_id = aws_security_group.ecs_task.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "tcp"
  from_port         = 443
  to_port           = 443
  description       = "AWS API calls over HTTPS (ECR, SSM, CloudWatch Logs, S3, Translate; SES pending 3D decision)"
}

resource "aws_security_group" "rds" {
  name        = "${var.project}-${var.environment}-rds-sg"
  description = "RDS security group"
  vpc_id      = var.vpc_id
  tags        = var.tags
}

resource "aws_vpc_security_group_egress_rule" "ecs_task_to_rds" {
  security_group_id            = aws_security_group.ecs_task.id
  referenced_security_group_id = aws_security_group.rds.id
  ip_protocol                  = "tcp"
  from_port                    = 3306
  to_port                      = 3306
  description                  = "Allow MySQL connections from ECS tasks to RDS"
}

resource "aws_vpc_security_group_ingress_rule" "rds_from_ecs_task" {
  security_group_id            = aws_security_group.rds.id
  referenced_security_group_id = aws_security_group.ecs_task.id
  ip_protocol                  = "tcp"
  from_port                    = 3306
  to_port                      = 3306
  description                  = "Allow MySQL connections from ECS tasks"
}

resource "aws_db_subnet_group" "this" {
  name       = "${var.project}-${var.environment}-db-subnet-group"
  subnet_ids = var.private_subnet_ids
  tags       = var.tags
}
