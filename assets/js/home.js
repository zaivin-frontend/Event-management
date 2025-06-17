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
const forms = document.querySelectorAll("form");
forms.forEach((form) => {
  form.addEventListener("submit", function (event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add("was-validated");
  });
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute("href"));
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }
  });
});

// Add animation on scroll
const animateOnScroll = () => {
  const elements = document.querySelectorAll(".fade-in");
  elements.forEach((element) => {
    const elementTop = element.getBoundingClientRect().top;
    const elementBottom = element.getBoundingClientRect().bottom;

    if (elementTop < window.innerHeight && elementBottom > 0) {
      element.style.opacity = "1";
      element.style.transform = "translateY(0)";
    }
  });
};

// Initial check for elements in view
window.addEventListener("load", animateOnScroll);
// Check for elements in view on scroll
window.addEventListener("scroll", animateOnScroll);

// Navbar scroll effect
window.addEventListener("scroll", function () {
  const navbar = document.querySelector(".navbar");
  if (window.scrollY > 50) {
    navbar.classList.add("scrolled");
  } else {
    navbar.classList.remove("scrolled");
  }
});

// Handle admin login form submission
document
  .getElementById("adminLoginForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch(this.action, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.href = data.redirect;
        } else {
          // Show error message
          const errorAlert = document.createElement("div");
          errorAlert.className =
            "alert alert-danger alert-dismissible fade show";
          errorAlert.innerHTML = `
              <i class="bi bi-exclamation-circle me-2"></i>${data.error}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          `;

          // Remove any existing error alerts
          const existingAlert = this.querySelector(".alert");
          if (existingAlert) {
            existingAlert.remove();
          }

          // Insert new error alert at the top of the form
          this.insertBefore(errorAlert, this.firstChild);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        const errorAlert = document.createElement("div");
        errorAlert.className = "alert alert-danger alert-dismissible fade show";
        errorAlert.innerHTML = `
          <i class="bi bi-exclamation-circle me-2"></i>An error occurred. Please try again.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;

        // Remove any existing error alerts
        const existingAlert = this.querySelector(".alert");
        if (existingAlert) {
          existingAlert.remove();
        }

        // Insert new error alert at the top of the form
        this.insertBefore(errorAlert, this.firstChild);
      });
  });

// Handle admin login form submission
document
  .getElementById("adminLoginForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch(this.action, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.href = data.redirect;
        } else {
          // Show error message
          const errorAlert = document.createElement("div");
          errorAlert.className =
            "alert alert-danger alert-dismissible fade show";
          errorAlert.innerHTML = `
                        <i class="bi bi-exclamation-circle me-2"></i>${data.error}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;

          // Remove any existing error alerts
          const existingAlert = this.querySelector(".alert");
          if (existingAlert) {
            existingAlert.remove();
          }

          // Insert new error alert at the top of the form
          this.insertBefore(errorAlert, this.firstChild);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        const errorAlert = document.createElement("div");
        errorAlert.className = "alert alert-danger alert-dismissible fade show";
        errorAlert.innerHTML = `
                    <i class="bi bi-exclamation-circle me-2"></i>An error occurred. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

        // Remove any existing error alerts
        const existingAlert = this.querySelector(".alert");
        if (existingAlert) {
          existingAlert.remove();
        }

        // Insert new error alert at the top of the form
        this.insertBefore(errorAlert, this.firstChild);
      });
  });
