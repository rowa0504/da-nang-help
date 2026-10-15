module "network" {
  source = "../../modules/network"

  project              = var.project
  environment          = var.environment
  vpc_cidr             = var.vpc_cidr
  availability_zones   = var.availability_zones
  public_subnet_cidrs  = var.public_subnet_cidrs
  private_subnet_cidrs = var.private_subnet_cidrs
}

# Looked up by name, not created here — the shared ECR repository is
# owned exclusively by environments/shared (see that directory's
# main.tf). This `plan` fails until the shared environment's ECR has
# actually been applied; that is expected, see infra/terraform/README.md.
data "aws_ecr_repository" "app" {
  name = var.ecr_repository_name
}

module "s3" {
  source = "../../modules/s3"

  bucket_name   = var.photos_bucket_name
  force_destroy = var.photos_bucket_force_destroy
}

module "logging" {
  source = "../../modules/logging"

  project           = var.project
  environment       = var.environment
  retention_in_days = var.log_retention_days
}

module "iam" {
  source = "../../modules/iam"

  project               = var.project
  environment           = var.environment
  ecr_repository_arn    = data.aws_ecr_repository.app.arn
  s3_bucket_arn         = module.s3.bucket_arn
  log_group_stream_arns = values(module.logging.log_group_stream_arns)
  ssm_path_prefix       = var.ssm_path_prefix
}
