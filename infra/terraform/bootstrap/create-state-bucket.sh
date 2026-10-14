#!/usr/bin/env bash
#
# Creates — or, if it already exists, verifies and repairs — the S3
# bucket used as the Terraform remote state backend for this project.
#
# This script is idempotent and NEVER deletes anything: there is no
# delete-bucket, delete-object, or delete-policy code path in it at all.
# If the bucket already exists under THIS account, it re-asserts the
# required settings (versioning, encryption, public access block,
# ownership controls, TLS-only policy, lifecycle) rather than assuming
# "exists == already configured correctly".
#
# This bucket is for Terraform state ONLY. Do not delete it. Do not
# store anything else in it.
#
# Required environment variables (no defaults — the bucket name is never
# hardcoded in this script, and must be chosen for global S3 uniqueness
# before running this):
#   STATE_BUCKET_NAME  - the globally-unique bucket name to create/verify
#   AWS_REGION         - e.g. ap-southeast-1
#   AWS_PROFILE        - the AWS CLI profile to use (an IAM Identity
#                         Center/STS-backed profile — never a long-lived
#                         access key; see RELEASE_PLAN.md 4.6/4.7.4)
#
# Usage:
#   STATE_BUCKET_NAME=dananghelp-terraform-state-<unique-suffix> \
#   AWS_REGION=ap-southeast-1 \
#   AWS_PROFILE=danang-help-admin \
#   ./create-state-bucket.sh
#
# This script has not been executed as part of writing it. Review it
# before running, and confirm `aws sts get-caller-identity` (printed
# first, below) shows the expected account before anything else happens.
#
# HeadBucket decision table (see infra/terraform/README.md for the same
# table — keep both in sync if this logic ever changes):
#
#   HeadBucket result          | Action
#   ----------------------------|---------------------------------------
#   200 (success)               | Ours: verify ownership/region, then
#                                | repair/re-apply configuration
#   404 Not Found                | Does not exist: create it
#   403 Forbidden                 | Someone else's bucket, or no
#                                | permission: abort immediately
#   301 / 400 / anything else    | Ambiguous: abort immediately, never
#   unparseable                   | assume "safe to create"

set -Eeuo pipefail

: "${STATE_BUCKET_NAME:?Set STATE_BUCKET_NAME to the globally-unique bucket name to create/verify}"
: "${AWS_REGION:?Set AWS_REGION, e.g. ap-southeast-1}"
: "${AWS_PROFILE:?Set AWS_PROFILE to the IAM Identity Center/STS profile to use}"

aws_cli() {
    aws --no-cli-pager --region "${AWS_REGION}" --profile "${AWS_PROFILE}" "$@"
}

echo "== Target AWS account/identity (confirm this is the expected account before continuing) =="
aws_cli sts get-caller-identity
ACCOUNT_ID=$(aws_cli sts get-caller-identity --query Account --output text)

echo
echo "== Checking whether s3://${STATE_BUCKET_NAME} exists and is owned by this account (${ACCOUNT_ID}) =="
# --expected-bucket-owner: if the bucket exists but belongs to a
# different account, AWS returns 403 even though we might otherwise be
# able to reach it some other way — this is exactly the "someone else
# already took this name" case we must never mistake for "safe to
# create" or "safe to reconfigure".
set +e
HEAD_OUTPUT=$(aws_cli s3api head-bucket --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}" 2>&1)
HEAD_STATUS=$?
set -e

BUCKET_EXISTS=false
if [ "${HEAD_STATUS}" -eq 0 ]; then
    echo "200: bucket exists and is owned by this account (${ACCOUNT_ID})."
    BUCKET_EXISTS=true
elif echo "${HEAD_OUTPUT}" | grep -Eq '\(403\)'; then
    echo "ERROR: HeadBucket returned 403 Forbidden." >&2
    echo "This means either the bucket belongs to a DIFFERENT AWS account," >&2
    echo "or this principal lacks permission to access it. Either way, this" >&2
    echo "is not safe to create or modify. Choose a different" >&2
    echo "STATE_BUCKET_NAME. Aborting without making any changes." >&2
    exit 1
elif echo "${HEAD_OUTPUT}" | grep -Eq '\(404\)'; then
    echo "404: bucket does not exist yet. Will create it."
elif echo "${HEAD_OUTPUT}" | grep -Eq '\(301\)|\(400\)'; then
    echo "ERROR: HeadBucket returned 301 or 400 — ambiguous (e.g. a region" >&2
    echo "mismatch or malformed request), not a clean 404. Refusing to" >&2
    echo "assume it is safe to create. Investigate manually. Aborting" >&2
    echo "without making any changes." >&2
    echo "${HEAD_OUTPUT}" >&2
    exit 1
else
    echo "ERROR: unrecognized HeadBucket failure — refusing to guess what it means:" >&2
    echo "${HEAD_OUTPUT}" >&2
    exit 1
fi

if [ "${BUCKET_EXISTS}" = true ]; then
    echo
    echo "== Verifying the existing bucket's region matches AWS_REGION (${AWS_REGION}) =="
    EXISTING_REGION=$(aws_cli s3api get-bucket-location --bucket "${STATE_BUCKET_NAME}" \
        --expected-bucket-owner "${ACCOUNT_ID}" --query 'LocationConstraint' --output text)
    # get-bucket-location returns the literal string "None" for us-east-1
    # (a long-standing S3 API quirk) — not relevant here since this
    # project's region is ap-southeast-1, but handled for correctness.
    if [ "${EXISTING_REGION}" = "None" ]; then
        EXISTING_REGION="us-east-1"
    fi
    if [ "${EXISTING_REGION}" != "${AWS_REGION}" ]; then
        echo "ERROR: existing bucket is in region '${EXISTING_REGION}', but AWS_REGION is '${AWS_REGION}'." >&2
        echo "Refusing to reconfigure a bucket in an unexpected region. Aborting." >&2
        exit 1
    fi
    echo "Region matches (${EXISTING_REGION})."
else
    echo
    echo "== Creating s3://${STATE_BUCKET_NAME} in ${AWS_REGION} =="
    if [ "${AWS_REGION}" = "us-east-1" ]; then
        aws_cli s3api create-bucket --bucket "${STATE_BUCKET_NAME}"
    else
        aws_cli s3api create-bucket --bucket "${STATE_BUCKET_NAME}" \
            --create-bucket-configuration LocationConstraint="${AWS_REGION}"
    fi
fi

echo
echo "== Ensuring Versioning is enabled =="
aws_cli s3api put-bucket-versioning --bucket "${STATE_BUCKET_NAME}" \
    --expected-bucket-owner "${ACCOUNT_ID}" \
    --versioning-configuration Status=Enabled

echo
echo "== Ensuring default encryption (SSE-S3) =="
aws_cli s3api put-bucket-encryption --bucket "${STATE_BUCKET_NAME}" \
    --expected-bucket-owner "${ACCOUNT_ID}" \
    --server-side-encryption-configuration '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'

echo
echo "== Ensuring Public Access Block (all four settings enabled) =="
aws_cli s3api put-public-access-block --bucket "${STATE_BUCKET_NAME}" \
    --expected-bucket-owner "${ACCOUNT_ID}" \
    --public-access-block-configuration \
    BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

echo
echo "== Ensuring Object Ownership is BucketOwnerEnforced (disables ACLs entirely) =="
# This is a single-purpose bucket with no cross-account writers, so ACLs
# serve no purpose here; disabling them simplifies the ownership/access
# model to IAM + the bucket policy alone.
aws_cli s3api put-bucket-ownership-controls --bucket "${STATE_BUCKET_NAME}" \
    --expected-bucket-owner "${ACCOUNT_ID}" \
    --ownership-controls 'Rules=[{ObjectOwnership=BucketOwnerEnforced}]'

echo
echo "== Ensuring a TLS-only bucket policy (denies any request where aws:SecureTransport=false) =="
POLICY_FILE=$(mktemp)
LIFECYCLE_FILE=$(mktemp)
trap 'rm -f "${POLICY_FILE}" "${LIFECYCLE_FILE}"' EXIT

cat > "${POLICY_FILE}" <<POLICY
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "DenyInsecureTransport",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": [
        "arn:aws:s3:::${STATE_BUCKET_NAME}",
        "arn:aws:s3:::${STATE_BUCKET_NAME}/*"
      ],
      "Condition": {
        "Bool": {
          "aws:SecureTransport": "false"
        }
      }
    }
  ]
}
POLICY
aws_cli s3api put-bucket-policy --bucket "${STATE_BUCKET_NAME}" \
    --expected-bucket-owner "${ACCOUNT_ID}" \
    --policy "file://${POLICY_FILE}"

echo
echo "== Ensuring lifecycle policy (noncurrent state versions expire after 365 days — current/live state is never touched) =="
cat > "${LIFECYCLE_FILE}" <<'LIFECYCLE'
{
  "Rules": [
    {
      "ID": "expire-noncurrent-state-versions-after-365-days",
      "Status": "Enabled",
      "Filter": {},
      "NoncurrentVersionExpiration": {
        "NoncurrentDays": 365
      }
    }
  ]
}
LIFECYCLE
aws_cli s3api put-bucket-lifecycle-configuration --bucket "${STATE_BUCKET_NAME}" \
    --expected-bucket-owner "${ACCOUNT_ID}" \
    --lifecycle-configuration "file://${LIFECYCLE_FILE}"

echo
echo "== Final verification (read back every setting just applied) =="
echo "--- Versioning ---"
aws_cli s3api get-bucket-versioning --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}"
echo "--- Encryption ---"
aws_cli s3api get-bucket-encryption --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}"
echo "--- Public Access Block ---"
aws_cli s3api get-public-access-block --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}"
echo "--- Object Ownership Controls ---"
aws_cli s3api get-bucket-ownership-controls --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}"
echo "--- Bucket Policy ---"
aws_cli s3api get-bucket-policy --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}"
echo "--- Lifecycle Configuration ---"
aws_cli s3api get-bucket-lifecycle-configuration --bucket "${STATE_BUCKET_NAME}" --expected-bucket-owner "${ACCOUNT_ID}"

echo
echo "Done. s3://${STATE_BUCKET_NAME} is configured for Terraform state."
echo "Reminder: this bucket is for Terraform state ONLY. Do not delete it. Do not store anything else in it."
