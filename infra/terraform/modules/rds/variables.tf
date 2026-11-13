variable "project" {
  description = "Project name, used for resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Environment name."
  type        = string

  validation {
    condition     = contains(["production", "staging"], var.environment)
    error_message = "environment must be either \"production\" or \"staging\"."
  }
}

variable "db_subnet_group_name" {
  description = "module.security.db_subnet_group_name (3A output)."
  type        = string
}

variable "rds_security_group_id" {
  description = "module.security.rds_security_group_id (3A output)."
  type        = string
}

variable "master_username" {
  description = "RDS master username. This is NOT the application's runtime DB user — see the IAM boundary gate in infra/terraform/README.md (3D creates a separate, minimal-privilege app DB user)."
  type        = string

  validation {
    condition     = !contains(["root", "admin", "administrator", "master", "mysql", "sys", "rdsadmin", "sa"], lower(var.master_username))
    error_message = "master_username must not be a reserved or generic name (root, admin, administrator, master, mysql, sys, rdsadmin, sa)."
  }
}

variable "db_name" {
  description = "Initial database name created on the RDS instance; also published as SSM DB_DATABASE."
  type        = string
}

variable "db_password_version" {
  description = "Shared version marker for password_wo_version (RDS) and value_wo_version (SSM DB_MASTER_PASSWORD). Increment to rotate — see infra/terraform/README.md recovery procedure."
  type        = number
  default     = 1

  validation {
    condition     = var.db_password_version >= 1
    error_message = "db_password_version must be >= 1."
  }
}

variable "allocated_storage" {
  description = "Storage size in GiB."
  type        = number
  default     = 20

  validation {
    condition     = var.allocated_storage >= 20
    error_message = "allocated_storage must be >= 20 (gp3 minimum for db.t4g.micro, confirmed via AWS CLI)."
  }
}

variable "backup_retention_period" {
  description = "Automated backup retention, in days."
  type        = number
  default     = 7

  validation {
    condition     = var.backup_retention_period >= 1 && var.backup_retention_period <= 35
    error_message = "backup_retention_period must be between 1 and 35 days."
  }
}

variable "deletion_protection" {
  description = "production=true, staging=false. Not fixed in this module so a future temporary staging apply can still be destroyed normally — see infra/terraform/README.md."
  type        = bool
}

variable "final_snapshot_identifier" {
  description = "Unique final snapshot identifier used on destroy. Must be updated explicitly at deletion-approval time and never reused (timestamp() is not used — it would cause a diff on every plan). Check for an existing snapshot of the same name before destroying."
  type        = string

  validation {
    condition     = length(trimspace(var.final_snapshot_identifier)) > 0 && !startswith(var.final_snapshot_identifier, "REPLACE_WITH_")
    error_message = "final_snapshot_identifier must not be empty or a REPLACE_WITH_ placeholder."
  }
}

variable "backup_window" {
  description = "Daily automated backup window, UTC, format HH:MM-HH:MM."
  type        = string

  validation {
    condition     = can(regex("^([01][0-9]|2[0-3]):[0-5][0-9]-([01][0-9]|2[0-3]):[0-5][0-9]$", var.backup_window))
    error_message = "backup_window must match the format HH:MM-HH:MM (UTC, 24-hour)."
  }
}

variable "maintenance_window" {
  description = "Weekly maintenance window, UTC, format ddd:HH:MM-ddd:HH:MM."
  type        = string

  validation {
    condition     = can(regex("^(mon|tue|wed|thu|fri|sat|sun):([01][0-9]|2[0-3]):[0-5][0-9]-(mon|tue|wed|thu|fri|sat|sun):([01][0-9]|2[0-3]):[0-5][0-9]$", var.maintenance_window))
    error_message = "maintenance_window must match the format ddd:HH:MM-ddd:HH:MM (UTC, 24-hour, e.g. mon:20:00-mon:21:00)."
  }
}

variable "tags" {
  description = "Additional tags to merge onto every resource this module creates, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
