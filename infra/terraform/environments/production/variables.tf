variable "project" {
  description = "Project name, used for resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name."
  type        = string

  validation {
    condition     = var.environment == "production"
    error_message = "This environment directory (environments/production) is for the production environment only."
  }
}

variable "aws_region" {
  description = "AWS region to deploy into."
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for the production VPC."
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
  description = "Whether the photos bucket can be destroyed while non-empty. Must stay false for production."
  type        = bool
  default     = false
}

variable "log_retention_days" {
  description = "CloudWatch Logs retention, shared by all 4 containers."
  type        = number
  default     = 30
}

variable "ssm_path_prefix" {
  description = "SSM parameter path prefix the Task Execution Role may read, e.g. \"/dananghelp/production\"."
  type        = string
}

variable "master_username" {
  description = "RDS master username (see modules/rds variable for the reserved-name validation). Not the application's runtime DB user."
  type        = string
}

variable "db_name" {
  description = "Initial database name created on the RDS instance."
  type        = string
}

variable "db_password_version" {
  description = "Shared version marker for the RDS master password's write-only attributes. Increment to rotate."
  type        = number
  default     = 1
}

variable "rds_allocated_storage" {
  description = "RDS storage size in GiB."
  type        = number
  default     = 20
}

variable "rds_backup_retention_period" {
  description = "RDS automated backup retention, in days."
  type        = number
  default     = 7
}

variable "rds_deletion_protection" {
  description = "Must stay true for production."
  type        = bool
  default     = true
}

variable "rds_final_snapshot_identifier" {
  description = "Unique final snapshot identifier used on destroy. Update explicitly at deletion-approval time."
  type        = string
}

variable "rds_backup_window" {
  description = "Daily automated backup window, UTC, format HH:MM-HH:MM."
  type        = string
}

variable "rds_maintenance_window" {
  description = "Weekly maintenance window, UTC, format ddd:HH:MM-ddd:HH:MM."
  type        = string
}
