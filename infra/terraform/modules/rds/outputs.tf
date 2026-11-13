# No output here ever exposes the master password's value — the
# ephemeral resource has no readable attribute that could leak into
# plan/state/output in the first place.

output "db_instance_identifier" {
  value = aws_db_instance.this.id
}

output "db_instance_address" {
  value = aws_db_instance.this.address
}

output "db_instance_port" {
  value = aws_db_instance.this.port
}

output "ssm_parameter_names" {
  description = "Names only (not values) of the 5 SSM parameters this module creates."
  value = {
    db_host            = aws_ssm_parameter.db_host.name
    db_port            = aws_ssm_parameter.db_port.name
    db_database        = aws_ssm_parameter.db_database.name
    db_master_username = aws_ssm_parameter.db_master_username.name
    db_master_password = aws_ssm_parameter.db_master_password.name
  }
}
