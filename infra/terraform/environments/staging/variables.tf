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
