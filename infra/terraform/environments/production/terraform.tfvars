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
