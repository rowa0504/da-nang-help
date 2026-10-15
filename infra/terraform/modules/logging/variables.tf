variable "project" {
  description = "Project name, used for log group naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. \"production\", \"staging\")."
  type        = string
}

variable "retention_in_days" {
  description = "CloudWatch Logs retention, shared by all 4 containers."
  type        = number
  default     = 30
}

variable "tags" {
  description = "Additional tags to merge onto every log group this module creates, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
