export const validators = {
  email(value: string): boolean {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return regex.test(value)
  },

  url(value: string): boolean {
    try {
      new URL(value)
      return true
    } catch {
      return false
    }
  },

  ipAddress(value: string): boolean {
    const regex =
      /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/
    return regex.test(value)
  },

  hostname(value: string): boolean {
    const regex =
      /^(?!-)[a-zA-Z0-9-]{1,63}(?<!-)(\.[a-zA-Z0-9-]{1,63})*\.[a-zA-Z]{2,}$/
    return regex.test(value)
  },

  port(value: string): boolean {
    const num = parseInt(value)
    return !isNaN(num) && num > 0 && num <= 65535
  },

  password(value: string): { valid: boolean; errors: string[] } {
    const errors: string[] = []
    if (value.length < 8) errors.push('Minimum 8 characters')
    if (!/[a-z]/.test(value)) errors.push('Must include lowercase')
    if (!/[A-Z]/.test(value)) errors.push('Must include uppercase')
    if (!/[0-9]/.test(value)) errors.push('Must include number')
    if (!/[!@#$%^&*]/.test(value)) errors.push('Must include special character')
    return {
      valid: errors.length === 0,
      errors
    }
  },

  required(value: any): boolean {
    if (value === null || value === undefined) return false
    if (typeof value === 'string') return value.trim().length > 0
    if (Array.isArray(value)) return value.length > 0
    return true
  }
}

export interface ValidationError {
  field: string
  message: string
}

export function validateForm(data: Record<string, any>, rules: Record<string, any>): ValidationError[] {
  const errors: ValidationError[] = []

  Object.entries(rules).forEach(([field, rule]) => {
    const value = data[field]
    
    if (rule.required && !validators.required(value)) {
      errors.push({ field, message: `${field} is required` })
      return
    }

    if (rule.type === 'email' && !validators.email(value)) {
      errors.push({ field, message: 'Invalid email format' })
    }

    if (rule.type === 'url' && !validators.url(value)) {
      errors.push({ field, message: 'Invalid URL format' })
    }

    if (rule.type === 'password') {
      const result = validators.password(value)
      if (!result.valid) {
        errors.push({ field, message: result.errors.join(', ') })
      }
    }

    if (rule.minLength && value.length < rule.minLength) {
      errors.push({ field, message: `Minimum ${rule.minLength} characters` })
    }

    if (rule.maxLength && value.length > rule.maxLength) {
      errors.push({ field, message: `Maximum ${rule.maxLength} characters` })
    }
  })

  return errors
}
