variable "project" {
  description = "Project name, used for role naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. \"production\", \"staging\")."
  type        = string
}

variable "ecr_repository_arn" {
  description = "ARN of the shared ECR repository (see environments/shared), looked up by the calling environment via a data source."
  type        = string
}

variable "s3_bucket_arn" {
  description = "ARN of this environment's Service Request photos bucket (modules/s3 bucket_arn output)."
  type        = string
}

variable "log_group_stream_arns" {
  description = "Normalized (exactly one trailing \":*\") CloudWatch Log Group ARNs — modules/logging's log_group_stream_arns output, never its raw log_group_arns."
  type        = list(string)
}

variable "ssm_path_prefix" {
  description = "SSM parameter path prefix this Task Execution Role may read, e.g. \"/dananghelp/production\". The trailing \"/*\" is added internally."
  type        = string
}

variable "tags" {
  description = "Additional tags to merge onto every resource this module creates, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
