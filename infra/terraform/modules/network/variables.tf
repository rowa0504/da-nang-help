variable "project" {
  description = "Project name, used for resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. \"production\", \"staging\")."
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for the VPC."
  type        = string
}

# At least 2 AZs: the ALB and the RDS DB Subnet Group both require
# subnets in 2+ Availability Zones, even for a Single-AZ RDS instance
# (RELEASE_PLAN.md 4.6).
variable "availability_zones" {
  description = "Availability Zones to spread subnets across. Must be the same length as public_subnet_cidrs and private_subnet_cidrs (one of each per AZ, same order)."
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

variable "tags" {
  description = "Additional tags to merge onto every resource this module creates, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
