import test from 'node:test'
import assert from 'node:assert/strict'
import { isOtpFlowAllowed, canAccessResetPassword } from '../app/utils/auth-flow.js'

test('allows OTP flow only when the stored flow matches the current email and purpose', () => {
  assert.equal(isOtpFlowAllowed({ email: 'user@example.com', purpose: 'register' }, 'user@example.com', 'register'), true)
  assert.equal(isOtpFlowAllowed({ email: 'user@example.com', purpose: 'register' }, 'other@example.com', 'register'), false)
  assert.equal(isOtpFlowAllowed({ email: 'user@example.com', purpose: 'register' }, 'user@example.com', 'reset'), false)
})

test('allows reset-password only after a reset code has been verified', () => {
  assert.equal(canAccessResetPassword('user@example.com', '123456'), true)
  assert.equal(canAccessResetPassword('', '123456'), false)
  assert.equal(canAccessResetPassword('user@example.com', ''), false)
})
