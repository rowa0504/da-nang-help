variable "project" {
  description = "Project name, used for resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name."
  type        = string

  validation {
    condition     = var.environment == "staging"
    error_message = "This environment directory (environments/staging) is for the staging (temporary verification, RELEASE_PLAN.md section 7) environment only."
  }
}

variable "aws_region" {
  description = "AWS region to deploy into."
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for the staging VPC."
  type        = string
}

variable "availability_zones" {
  description = "Availability Zones to spread subnets across."
  type        = list(string)

  validation {
    condition     = length(var.availability_zones) >= 2
    error_message = "At least 2 Availability Zones are required (the ALB and the RDS DB Subnet Group both require subnets in 2+ AZs)."
  }
}

variable "public_subnet_cidrs" {
  description = "Public subnet CIDRs, one per entry in availability_zones, in the same order."
  type        = list(string)

  validation {
    condition     = length(var.public_subnet_cidrs) == length(var.availability_zones)
    error_message = "public_subnet_cidrs must have exactly one CIDR per entry in availability_zones."
  }
}

variable "private_subnet_cidrs" {
  description = "Private subnet CIDRs, one per entry in availability_zones, in the same order."
  type        = list(string)

  validation {
    condition     = length(var.private_subnet_cidrs) == length(var.availability_zones)
    error_message = "private_subnet_cidrs must have exactly one CIDR per entry in availability_zones."
  }
}

variable "ecr_repository_name" {
  description = "Name of the shared ECR repository (owned by environments/shared), looked up by data source."
  type        = string
}

variable "photos_bucket_name" {
  description = "Globally-unique S3 bucket name for Service Request photos."
  type        = string

  validation {
    condition     = !startswith(var.photos_bucket_name, "REPLACE_WITH_")
    error_message = "photos_bucket_name still has its placeholder value. Set a real, globally-unique bucket name before running plan/apply."
  }
}

variable "photos_bucket_force_destroy" {
  description = "Whether the photos bucket can be destroyed while non-empty. staging is a temporary verification environment, so this stays true."
  type        = bool
  default     = true
}

variable "log_retention_days" {
  description = "CloudWatch Logs retention, shared by all 4 containers."
  type        = number
  default     = 30
}

variable "ssm_path_prefix" {
  description = "SSM parameter path prefix the Task Execution Role may read, e.g. \"/dananghelp/staging\"."
  type        = string
}
