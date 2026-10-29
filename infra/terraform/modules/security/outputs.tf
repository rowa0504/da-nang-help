output "alb_security_group_id" {
  value = aws_security_group.alb.id
}

output "ecs_task_security_group_id" {
  value = aws_security_group.ecs_task.id
}

output "rds_security_group_id" {
  value = aws_security_group.rds.id
}

output "db_subnet_group_name" {
  value = aws_db_subnet_group.this.name
}
