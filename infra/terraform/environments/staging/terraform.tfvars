project     = "dananghelp"
environment = "staging"
aws_region  = "ap-southeast-1"

# Distinct from production's 10.0.0.0/16 so that, if both were ever
# temporarily up at the same time, their CIDRs would never collide.
vpc_cidr = "10.1.0.0/16"

availability_zones = [
  "ap-southeast-1a",
  "ap-southeast-1b",
]

public_subnet_cidrs = [
  "10.1.0.0/24",
  "10.1.1.0/24",
]

private_subnet_cidrs = [
  "10.1.10.0/24",
  "10.1.11.0/24",
]

ecr_repository_name = "dananghelp-app"

# Must be globally unique. Replace before running plan/apply — the
# variable validation in variables.tf rejects this placeholder.
photos_bucket_name = "dananghelp-staging-photos-fd3e306cc30d"

photos_bucket_force_destroy = true
log_retention_days          = 30
ssm_path_prefix             = "/dananghelp/staging"

# RDS (3B). No secret values here — the master password is generated
# at apply time via an ephemeral resource and never written to a
# tfvars file, state, or output. See infra/terraform/README.md.
master_username = "dananghelp_master"
db_name         = "dananghelp"

db_password_version = 1

rds_allocated_storage       = 20
rds_backup_retention_period = 7
# staging is a temporary verification environment, so this stays
# false (so a temporary apply can still be destroyed normally).
rds_deletion_protection = false

# Unique per final snapshot — update explicitly at deletion-approval
# time, never reuse. See infra/terraform/README.md.
rds_final_snapshot_identifier = "dananghelp-staging-final-20261102"

# UTC. 19:30-20:00 UTC = 02:30-03:00 ICT (Da Nang), a low-traffic hour,
# offset from production's window so the two never collide.
rds_backup_window = "19:30-20:00"
# UTC. tue:20:30-tue:21:30 UTC = Tue 03:30-04:30 ICT (Da Nang). Does
# not overlap rds_backup_window above, and falls on a different day
# from production's maintenance window.
rds_maintenance_window = "tue:20:30-tue:21:30"
