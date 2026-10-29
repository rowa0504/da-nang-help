output "vpc_id" {
  value = module.network.vpc_id
}

output "public_subnet_ids" {
  value = module.network.public_subnet_ids
}

output "private_subnet_ids" {
  value = module.network.private_subnet_ids
}

output "ecr_repository_url" {
  value = data.aws_ecr_repository.app.repository_url
}

output "photos_bucket_id" {
  value = module.s3.bucket_id
}

output "log_group_names" {
  value = module.logging.log_group_names
}

output "task_execution_role_arn" {
  value = module.iam.task_execution_role_arn
}

output "task_role_arn" {
  value = module.iam.task_role_arn
}

output "alb_security_group_id" {
  value = module.security.alb_security_group_id
}

output "ecs_task_security_group_id" {
  value = module.security.ecs_task_security_group_id
}

output "rds_security_group_id" {
  value = module.security.rds_security_group_id
}

output "db_subnet_group_name" {
  value = module.security.db_subnet_group_name
}
