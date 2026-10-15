variable "project" {
  description = "Project name, used for resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name."
  type        = string

  validation {
    condition     = var.environment == "shared"
    error_message = "This environment directory (environments/shared) is for account-wide resources shared by production and staging only (currently: the ECR repository)."
  }
}

variable "aws_region" {
  description = "AWS region to deploy into."
  type        = string
}

variable "ecr_repository_name" {
  description = "Name of the shared ECR repository. production/staging look this up by name via a data source — never change this value without updating their terraform.tfvars in the same change."
  type        = string
}

variable "ecr_lifecycle_keep_tagged_count" {
  description = "Number of most-recent sha-tagged images to keep."
  type        = number
  default     = 20
}

variable "ecr_lifecycle_untagged_expire_days" {
  description = "Days after which an untagged image is expired."
  type        = number
  default     = 7
}
