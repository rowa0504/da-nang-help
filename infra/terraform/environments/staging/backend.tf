# Partial backend configuration on purpose: the real bucket name is not
# known/committed yet (it must be globally unique — see
# infra/terraform/README.md). Supply the rest via:
#   terraform init -backend-config=backend.hcl
# where backend.hcl is a gitignored copy of backend.hcl.example with the
# real bucket name filled in.
terraform {
  backend "s3" {}
}
