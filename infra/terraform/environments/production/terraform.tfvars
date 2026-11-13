project     = "dananghelp"
environment = "production"
aws_region  = "ap-southeast-1"

vpc_cidr = "10.0.0.0/16"

availability_zones = [
  "ap-southeast-1a",
  "ap-southeast-1b",
]

public_subnet_cidrs = [
  "10.0.0.0/24",
  "10.0.1.0/24",
]

private_subnet_cidrs = [
  "10.0.10.0/24",
  "10.0.11.0/24",
]

ecr_repository_name = "dananghelp-app"

# Must be globally unique. Replace before running plan/apply — the
# variable validation in variables.tf rejects this placeholder.
photos_bucket_name = "dananghelp-production-photos-fd3e306cc30d"

photos_bucket_force_destroy = false
log_retention_days          = 30
ssm_path_prefix             = "/dananghelp/production"

# RDS (3B). No secret values here — the master password is generated
# at apply time via an ephemeral resource and never written to a
# tfvars file, state, or output. See infra/terraform/README.md.
master_username = "dananghelp_master"
db_name         = "dananghelp"

db_password_version = 1

rds_allocated_storage       = 20
rds_backup_retention_period = 7
rds_deletion_protection     = true

# Unique per final snapshot — update explicitly at deletion-approval
# time, never reuse. See infra/terraform/README.md.
rds_final_snapshot_identifier = "dananghelp-production-final-20261102"

# UTC. 19:00-19:30 UTC = 02:00-02:30 ICT (Da Nang), a low-traffic hour.
rds_backup_window = "19:00-19:30"
# UTC. mon:20:00-mon:21:00 UTC = Mon 03:00-04:00 ICT (Da Nang). Does
# not overlap rds_backup_window above.
rds_maintenance_window = "mon:20:00-mon:21:00"
