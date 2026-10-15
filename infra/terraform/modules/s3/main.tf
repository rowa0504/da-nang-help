# Service Request photo storage. Deliberately does NOT configure:
#
#   - CORS: every read and write goes through the Laravel application —
#     uploads are processed server-side (ProcessUploadedPhoto ->
#     Storage::disk()->put(), from PHP-FPM/the Task Role's own
#     credentials), and reads are presigned GET URLs
#     (Storage::disk()->temporaryUrl(), 15-minute expiry) opened
#     directly as <img src=...>. There is no browser-side direct-to-S3
#     upload and no cross-origin JS fetch/XHR against this bucket, so no
#     CORS rule is needed for the app to function.
#
#   - Versioning: intentionally never enabled, and no
#     aws_s3_bucket_versioning resource exists here at all. Once
#     versioning is enabled on a bucket it can only be moved to
#     "Suspended", never back to true "Unversioned" — and this
#     project's published Privacy Policy (resources/js/legal/*.ts,
#     section on deletion requests) describes photo deletion as an
#     actual delete, not something that leaves a recoverable prior
#     version sitting in the bucket indefinitely. The application's own
#     deletion logic (CreateServiceRequestAction/
#     UpdateServiceRequestAction) is a plain DeleteObject tied 1:1 to a
#     request_photos row deletion; versioning would silently undermine
#     that.

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
    }
  }
}

resource "aws_s3_bucket" "photos" {
  bucket        = var.bucket_name
  force_destroy = var.force_destroy

  tags = var.tags
}

resource "aws_s3_bucket_public_access_block" "photos" {
  bucket = aws_s3_bucket.photos.id

  block_public_acls       = true
  ignore_public_acls      = true
  block_public_policy     = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_ownership_controls" "photos" {
  bucket = aws_s3_bucket.photos.id

  rule {
    object_ownership = "BucketOwnerEnforced"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "photos" {
  bucket = aws_s3_bucket.photos.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# Denies any request made without TLS. The aws:PrincipalIsAWSService
# condition key (ANDed with aws:SecureTransport in the same Bool block)
# means an AWS service principal is never caught by this Deny even if
# it happens to connect without TLS — only non-service (IAM user/role)
# callers are held to this requirement.
resource "aws_s3_bucket_policy" "photos" {
  bucket = aws_s3_bucket.photos.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "DenyInsecureTransport"
        Effect    = "Deny"
        Principal = "*"
        Action    = "s3:*"
        Resource = [
          aws_s3_bucket.photos.arn,
          "${aws_s3_bucket.photos.arn}/*",
        ]
        Condition = {
          Bool = {
            "aws:SecureTransport"       = "false"
            "aws:PrincipalIsAWSService" = "false"
          }
        }
      }
    ]
  })
}

resource "aws_s3_bucket_lifecycle_configuration" "photos" {
  bucket = aws_s3_bucket.photos.id

  rule {
    id     = "abort-incomplete-multipart-uploads"
    status = "Enabled"

    filter {}

    abort_incomplete_multipart_upload {
      days_after_initiation = var.incomplete_multipart_upload_days
    }
  }
}
