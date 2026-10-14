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
