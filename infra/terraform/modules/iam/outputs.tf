output "task_execution_role_arn" {
  description = "ARN of the ECS Task Execution Role (startup-time: image pull, log stream creation, SSM parameter resolution)."
  value       = aws_iam_role.task_execution.arn
}

output "task_role_arn" {
  description = "ARN of the ECS Task Role (runtime: Amazon Translate, Service Request photos S3 bucket)."
  value       = aws_iam_role.task.arn
}
