variable "bucket_name" {
  description = "Globally-unique S3 bucket name for Service Request photos."
  type        = string

  validation {
    # Catches the committed terraform.tfvars placeholder so plan/apply
    # cannot silently proceed against it once a real name is supposed to
    # have been filled in.
    condition     = !startswith(var.bucket_name, "REPLACE_WITH_")
    error_message = "bucket_name is still the placeholder value. Set the real, globally-unique bucket name in terraform.tfvars before plan/apply."
  }
}

variable "force_destroy" {
  description = "Whether `terraform destroy` may delete this bucket even if it still contains objects. Must be false for production; true only for staging (a temporary, disposable environment per docs/RELEASE_PLAN.md section 7)."
  type        = bool
  default     = false
}

variable "incomplete_multipart_upload_days" {
  description = "Days after which an incomplete multipart upload is aborted."
  type        = number
  default     = 7
}

variable "tags" {
  description = "Additional tags to merge onto every resource this module creates, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
