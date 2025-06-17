// Format Student ID
function formatStudentId(input) {
  // Remove any non-digit characters
  let value = input.value.replace(/\D/g, "");

  // Limit to 7 digits
  value = value.substring(0, 7);

  // Add hyphen after first 2 digits
  if (value.length > 2) {
    value = value.substring(0, 2) + "-" + value.substring(2);
  }

  // Update input value
  input.value = value;
}

// Toggle password visibility
function togglePassword(button) {
  const input = button.parentElement.querySelector("input");
  const icon = button.querySelector("i");

  if (input.type === "password") {
    input.type = "text";
    icon.classList.replace("bi-eye", "bi-eye-slash");
  } else {
    input.type = "password";
    icon.classList.replace("bi-eye-slash", "bi-eye");
  }
}

// Form validation
const form = document.querySelector("form");
form.addEventListener("submit", function (event) {
  if (!form.checkValidity()) {
    event.preventDefault();
    event.stopPropagation();
  }
  form.classList.add("was-validated");
});
