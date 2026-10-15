output "log_group_names" {
  value = { for k, v in aws_cloudwatch_log_group.this : k => v.name }
}

# Raw ARNs as returned by the provider — NOT guaranteed to be free of a
# trailing ":*" (CloudWatch Logs' own API sometimes includes one, and
# the AWS provider passes it through as-is). Use log_group_stream_arns
# below when building an IAM policy Resource list; do not pass these
# raw ARNs into an IAM policy directly.
output "log_group_arns" {
  value = { for k, v in aws_cloudwatch_log_group.this : k => v.arn }
}

# Normalized to always have EXACTLY one trailing ":*" regardless of
# whether the provider's own .arn attribute already included one
# (trimsuffix removes it only if present, then ":*" is appended
# unconditionally) — the correct form for an IAM policy Resource
# granting access to all log streams within each log group
# (logs:CreateLogStream/PutLogEvents).
output "log_group_stream_arns" {
  value = { for k, v in aws_cloudwatch_log_group.this : k => "${trimsuffix(v.arn, ":*")}:*" }
}
