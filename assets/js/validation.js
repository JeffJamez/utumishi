function validateNationalId(nationalId) {
  const cleanId = nationalId.toString().replace(/\s/g, "");

  if (!cleanId) {
    return { valid: false, message: "National ID is required" };
  }

  if (!/^\d{8}$/.test(cleanId)) {
    return { valid: false, message: "National ID must be exactly 8 digits" };
  }

  return { valid: true, message: "Valid National ID" };
}

function validatePhone(phone) {
  if (!phone) {
    return { valid: false, message: "Phone number is required" };
  }

  const cleanPhone = phone.replace(/[\s\-]/g, "");

  const patterns = [/^\+254[17]\d{8}$/, /^254[17]\d{8}$/, /^0[17]\d{8}$/];

  const isValid = patterns.some((pattern) => pattern.test(cleanPhone));

  if (!isValid) {
    return {
      valid: false,
      message: "Invalid phone number format. Use +254XXXXXXXXX or 07XXXXXXXX",
    };
  }

  return { valid: true, message: "Valid phone number" };
}

function validateEmail(email) {
  if (!email) {
    return { valid: true, message: "Email is optional" };
  }

  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!emailPattern.test(email)) {
    return { valid: false, message: "Invalid email format" };
  }

  return { valid: true, message: "Valid email" };
}

function validatePassword(password) {
  if (document.getElementById("loginForm")) {
    return { valid: true, message: "Valid password" };
  }

  if (!password) {
    return { valid: false, message: "Password is required" };
  }

  if (password.length < 8) {
    return { valid: false, message: "Password must be at least 8 characters" };
  }

  if (
    !/[A-Za-z]/.test(password) ||
    !/\d/.test(password) ||
    !/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
  ) {
    return {
      valid: false,
      message:
        "Password must contain at least one letter, one number, and one special character",
    };
  }

  return { valid: true, message: "Valid password" };
}

function validateName(name) {
  if (!name || name.trim().length === 0) {
    return { valid: false, message: "Name is required" };
  }

  const trimmedName = name.trim();

  if (trimmedName.length < 2) {
    return { valid: false, message: "Name must be at least 2 characters" };
  }

  if (trimmedName.length > 100) {
    return { valid: false, message: "Name must not exceed 100 characters" };
  }

  if (!/^[A-Za-z\s\-\'\.]+$/.test(trimmedName)) {
    return {
      valid: false,
      message:
        "Name can only contain letters, spaces, hyphens, apostrophes, and periods",
    };
  }

  return { valid: true, message: "Valid name" };
}

function validateOBNumber(obNumber) {
  if (!obNumber) {
    return { valid: false, message: "OB Number is required" };
  }

  const cleanOB = obNumber.trim().toUpperCase();

  if (!/^OB-[A-Z0-9]+-\d{4}-\d{5}$/.test(cleanOB)) {
    return {
      valid: false,
      message: "Invalid OB Number format. Expected: OB-XXXX-YYYY-NNNNN",
    };
  }

  return { valid: true, message: "Valid OB Number" };
}

function validateBadgeNumber(badgeNumber) {
  if (!badgeNumber) {
    return { valid: false, message: "Badge number is required" };
  }

  const cleanBadge = badgeNumber.trim().toUpperCase();

  if (!/^KPS-\d{4}$/.test(cleanBadge)) {
    return {
      valid: false,
      message: "Invalid badge number format. Expected: KPS-NNNN",
    };
  }

  return { valid: true, message: "Valid badge number" };
}

function validateFile(
  file,
  allowedTypes = ["pdf", "jpg", "jpeg", "png"],
  maxSize = 5242880,
) {
  if (!file) {
    return { valid: false, message: "File is required" };
  }

  if (file.size > maxSize) {
    const maxSizeMB = Math.round((maxSize / 1024 / 1024) * 10) / 10;
    return {
      valid: false,
      message: `File size exceeds maximum allowed (${maxSizeMB}MB)`,
    };
  }

  const fileExtension = file.name.toLowerCase().split(".").pop();
  if (!allowedTypes.includes(fileExtension)) {
    return {
      valid: false,
      message: `Invalid file type. Allowed types: ${allowedTypes.join(", ")}`,
    };
  }

  return { valid: true, message: "Valid file" };
}

function validateText(text, fieldName, minLength = 1, maxLength = 500) {
  if (!text || text.trim().length === 0) {
    return { valid: false, message: `${fieldName} is required` };
  }

  const trimmedText = text.trim();

  if (trimmedText.length < minLength) {
    return {
      valid: false,
      message: `${fieldName} must be at least ${minLength} characters`,
    };
  }

  if (trimmedText.length > maxLength) {
    return {
      valid: false,
      message: `${fieldName} must not exceed ${maxLength} characters`,
    };
  }

  return { valid: true, message: `Valid ${fieldName.toLowerCase()}` };
}

function validateNumber(number, fieldName, min = null, max = null) {
  if (number === "" || number === null || number === undefined) {
    return { valid: false, message: `${fieldName} is required` };
  }

  const numValue = parseFloat(number);

  if (isNaN(numValue)) {
    return { valid: false, message: `${fieldName} must be a valid number` };
  }

  if (min !== null && numValue < min) {
    return { valid: false, message: `${fieldName} must be at least ${min}` };
  }

  if (max !== null && numValue > max) {
    return { valid: false, message: `${fieldName} must not exceed ${max}` };
  }

  return { valid: true, message: `Valid ${fieldName.toLowerCase()}` };
}

function validateForm(formId, validationRules) {
  const form = document.getElementById(formId);
  if (!form) return false;

  let isValid = true;
  const errors = {};

  form.querySelectorAll(".form-error").forEach((error) => error.remove());
  form
    .querySelectorAll(".form-control")
    .forEach((control) => control.classList.remove("error"));

  for (const fieldName in validationRules) {
    const field = form.querySelector(`[name="${fieldName}"]`);
    if (!field) continue;

    const rules = validationRules[fieldName];
    const value = field.type === "checkbox" ? field.checked : field.value;

    for (const rule of rules) {
      let result = { valid: true, message: "" };

      switch (rule.type) {
        case "required":
          if (!value || (typeof value === "string" && value.trim() === "")) {
            result = {
              valid: false,
              message: rule.message || `${fieldName} is required`,
            };
          }
          break;

        case "national_id":
          result = validateNationalId(value);
          break;

        case "phone":
          result = validatePhone(value);
          break;

        case "email":
          result = validateEmail(value);
          break;

        case "password":
          result = validatePassword(value);
          break;

        case "name":
          result = validateName(value);
          break;

        case "min_length":
          if (value && value.length < rule.value) {
            result = {
              valid: false,
              message:
                rule.message ||
                `${fieldName} must be at least ${rule.value} characters`,
            };
          }
          break;

        case "max_length":
          if (value && value.length > rule.value) {
            result = {
              valid: false,
              message:
                rule.message ||
                `${fieldName} must not exceed ${rule.value} characters`,
            };
          }
          break;

        case "match":
          const matchField = form.querySelector(`[name="${rule.field}"]`);
          if (matchField && value !== matchField.value) {
            result = {
              valid: false,
              message: rule.message || `${fieldName} must match ${rule.field}`,
            };
          }
          break;
      }

      if (!result.valid) {
        isValid = false;
        errors[fieldName] = result.message;

        field.classList.add("error");

        const errorDiv = document.createElement("div");
        errorDiv.className = "form-error";
        errorDiv.textContent = result.message;
        field.parentNode.appendChild(errorDiv);

        break;
      }
    }
  }

  return isValid;
}

function setupInputFormatting() {
  document.querySelectorAll('input[name="national_id"]').forEach((input) => {
    input.addEventListener("input", function (e) {
      this.value = this.value.replace(/\D/g, "").substring(0, 8);
    });
  });

  document
    .querySelectorAll('input[name="phone"], input[type="tel"]')
    .forEach((input) => {
      input.addEventListener("input", function (e) {
        this.value = this.value.replace(/[^\d\+\s\-]/g, "");
      });
    });

  document.querySelectorAll('input[name="name"]').forEach((input) => {
    input.addEventListener("input", function (e) {
      this.value = this.value.replace(/[^A-Za-z\s\-\'\.]/g, "");
    });
  });

  document.querySelectorAll('input[name="badge_number"]').forEach((input) => {
    input.addEventListener("input", function (e) {
      this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, "");
    });
  });

  document.querySelectorAll('input[name="ob_number"]').forEach((input) => {
    input.addEventListener("input", function (e) {
      this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, "");
    });
  });
}

function setupRealTimeValidation() {
  document.querySelectorAll(".form-control").forEach((input) => {
    input.addEventListener("blur", function () {
      const fieldName = this.getAttribute("name");
      if (document.getElementById("loginForm") && fieldName === "password")
        return;

      let result = { valid: true, message: "" };

      this.classList.remove("error");
      const existingError = this.parentNode.querySelector(".form-error");
      if (existingError) existingError.remove();

      switch (fieldName) {
        case "national_id":
          if (this.value) result = validateNationalId(this.value);
          break;
        case "phone":
          if (this.value) result = validatePhone(this.value);
          break;
        case "email":
          if (this.value) result = validateEmail(this.value);
          break;
        case "password":
          if (this.value && !document.getElementById("loginForm"))
            result = validatePassword(this.value);
          break;
        case "name":
          if (this.value) result = validateName(this.value);
          break;
        case "ob_number":
          if (this.value) result = validateOBNumber(this.value);
          break;
        case "badge_number":
          if (this.value) result = validateBadgeNumber(this.value);
          break;
      }

      if (!result.valid) {
        this.classList.add("error");
        const errorDiv = document.createElement("div");
        errorDiv.className = "form-error";
        errorDiv.textContent = result.message;
        this.parentNode.appendChild(errorDiv);
      }
    });

    input.addEventListener("input", function () {
      this.classList.remove("error");
      const existingError = this.parentNode.querySelector(".form-error");
      if (existingError) existingError.remove();
    });
  });
}

document.addEventListener("DOMContentLoaded", function () {
  setupInputFormatting();
  setupRealTimeValidation();
});

const ChartUtils = {
  colors: {
    primary: "#3b82f6",
    success: "#22c55e",
    warning: "#f59e0b",
    danger: "#ef4444",
    info: "#06b6d4",
    purple: "#8b5cf6",
    pink: "#ec4899",
    gray: "#6b7280",
  },

  getColor: function (index) {
    const palette = [
      this.colors.primary,
      this.colors.success,
      this.colors.warning,
      this.colors.danger,
      this.colors.info,
      this.colors.purple,
      this.colors.pink,
      this.colors.gray,
    ];
    return palette[index % palette.length];
  },
};

if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    validateNationalId,
    validatePhone,
    validateEmail,
    validatePassword,
    validateName,
    validateOBNumber,
    validateBadgeNumber,
    validateFile,
    validateText,
    validateNumber,
    validateForm,
    ChartUtils,
  };
}
