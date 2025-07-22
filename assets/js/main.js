// Mobile Navigation Toggle
document.addEventListener("DOMContentLoaded", () => {
  const hamburger = document.querySelector(".hamburger")
  const navMenu = document.querySelector(".nav-menu")

  if (hamburger && navMenu) {
    hamburger.addEventListener("click", () => {
      hamburger.classList.toggle("active")
      navMenu.classList.toggle("active")
    })

    // Close menu when clicking on a link
    document.querySelectorAll(".nav-link").forEach((link) => {
      link.addEventListener("click", () => {
        hamburger.classList.remove("active")
        navMenu.classList.remove("active")
      })
    })
  }

  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })

  // Add scroll effect to navbar
  window.addEventListener("scroll", () => {
    const navbar = document.querySelector(".navbar")
    if (navbar) {
      if (window.scrollY > 50) {
        navbar.style.background = "rgba(255, 255, 255, 0.98)"
      } else {
        navbar.style.background = "rgba(255, 255, 255, 0.95)"
      }
    }
  })

  // Animate feature cards on scroll
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = "1"
        entry.target.style.transform = "translateY(0)"
      }
    })
  }, observerOptions)

  // Observe feature cards
  document.querySelectorAll(".feature-card").forEach((card) => {
    card.style.opacity = "0"
    card.style.transform = "translateY(30px)"
    card.style.transition = "opacity 0.6s ease, transform 0.6s ease"
    observer.observe(card)
  })

  // Form validation
  const forms = document.querySelectorAll("form")
  forms.forEach((form) => {
    form.addEventListener("submit", (e) => {
      const requiredFields = form.querySelectorAll("[required]")
      let isValid = true

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          isValid = false
          field.style.borderColor = "#dc3545"
        } else {
          field.style.borderColor = "#ddd"
        }
      })

      if (!isValid) {
        e.preventDefault()
        alert("Mohon lengkapi semua field yang wajib diisi")
      }
    })
  })

  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert")
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0"
      setTimeout(() => {
        alert.remove()
      }, 300)
    }, 5000)
  })
})

// Utility functions
function showLoading(element) {
  if (element) {
    element.innerHTML = '<div style="text-align: center; padding: 20px;">Memuat...</div>'
  }
}

function hideLoading() {
  // Remove loading indicators
  document.querySelectorAll("[data-loading]").forEach((el) => {
    el.removeAttribute("data-loading")
  })
}

// Real-time clock for dashboard
function updateClock() {
  const clockElements = document.querySelectorAll(".live-clock")
  clockElements.forEach((clock) => {
    const now = new Date()
    const timeString = now.toLocaleTimeString("id-ID")
    const dateString = now.toLocaleDateString("id-ID", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    })
    clock.innerHTML = `${dateString}<br>${timeString}`
  })
}

// Update clock every second if clock elements exist
if (document.querySelector(".live-clock")) {
  updateClock()
  setInterval(updateClock, 1000)
}

// Table sorting functionality
function sortTable(table, column, direction = "asc") {
  const tbody = table.querySelector("tbody")
  const rows = Array.from(tbody.querySelectorAll("tr"))

  rows.sort((a, b) => {
    const aVal = a.cells[column].textContent.trim()
    const bVal = b.cells[column].textContent.trim()

    if (direction === "asc") {
      return aVal.localeCompare(bVal)
    } else {
      return bVal.localeCompare(aVal)
    }
  })

  rows.forEach((row) => tbody.appendChild(row))
}

// Add click handlers to sortable table headers
document.querySelectorAll("th[data-sortable]").forEach((header) => {
  header.style.cursor = "pointer"
  header.addEventListener("click", function () {
    const table = this.closest("table")
    const column = Array.from(this.parentNode.children).indexOf(this)
    const currentDirection = this.dataset.direction || "asc"
    const newDirection = currentDirection === "asc" ? "desc" : "asc"

    // Reset all headers
    table.querySelectorAll("th").forEach((th) => {
      th.dataset.direction = ""
      th.style.background = ""
    })

    // Set current header
    this.dataset.direction = newDirection
    this.style.background = "#e9ecef"

    sortTable(table, column, newDirection)
  })
})
