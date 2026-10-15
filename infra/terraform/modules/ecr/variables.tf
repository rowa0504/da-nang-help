variable "repository_name" {
  description = "Name of the ECR repository. Shared across production and staging — created once by environments/shared, referenced by name (via a data source) elsewhere."
  type        = string
}

variable "lifecycle_keep_tagged_count" {
  description = "Number of sha-* tagged images to keep. Older sha-tagged images beyond this count are expired."
  type        = number
  default     = 20
}

variable "lifecycle_untagged_expire_days" {
  description = "Number of days after which untagged images are expired."
  type        = number
  default     = 7
}

variable "tags" {
  description = "Additional tags to merge onto this repository, on top of the calling environment's provider-level default_tags."
  type        = map(string)
  default     = {}
}
