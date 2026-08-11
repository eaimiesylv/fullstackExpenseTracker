export function isOtpFlowAllowed(flow, email, purpose) {
  if (!flow || !email || !purpose) {
    return false
  }

  return flow.email === email && flow.purpose === purpose
}

export function canAccessResetPassword(email, passwordResetOtp) {
  return Boolean(email && passwordResetOtp)
}
