variable "project" {
  description = "Project name, used for resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. \"production\", \"staging\")."
  type        = string
}

variable "vpc_id" {
  description = "module.network.vpc_id"
  type        = string
}

variable "private_subnet_ids" {
  description = "module.network.private_subnet_ids — the two private subnets registered on the DB Subnet Group."
  type        = list(string)

  validation {
    condition = (
      length(var.private_subnet_ids) == 2 &&
      length(distinct(var.private_subnet_ids)) == 2
    )
    error_message = "private_subnet_ids must contain exactly two distinct subnet IDs."
  }
}

variable "container_port" {
  description = "Port the Nginx container accepts requests from the ALB on."
  type        = number
  default     = 80
}

variable "tags" {
  description = "Additional tags to merge onto every resource this module creates, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
