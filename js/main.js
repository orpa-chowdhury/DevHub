document.addEventListener("DOMContentLoaded", () => {
  // Toggle dropdowns
  const dropdownToggles = document.querySelectorAll(".dropdown-toggle")

  dropdownToggles.forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.preventDefault()
      const parent = this.parentElement
      const menu = parent.querySelector(".dropdown-menu")

      // Close all other dropdowns
      document.querySelectorAll(".dropdown-menu").forEach((item) => {
        if (item !== menu && item.classList.contains("show")) {
          item.classList.remove("show")
        }
      })

      // Toggle current dropdown
      menu.classList.toggle("show")
    })
  })

  // Close dropdowns when clicking outside
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".dropdown")) {
      document.querySelectorAll(".dropdown-menu").forEach((menu) => {
        menu.classList.remove("show")
      })
    }
  })

  // File upload preview
  const fileInput = document.getElementById("file-upload")
  const filePreview = document.getElementById("file-preview")

  if (fileInput && filePreview) {
    fileInput.addEventListener("change", function () {
      const file = this.files[0]

      if (file) {
        const reader = new FileReader()

        reader.addEventListener("load", () => {
          filePreview.innerHTML = `
                        <div class="file-preview-item">
                            <div class="file-preview-name">${file.name}</div>
                            <div class="file-preview-size">${formatFileSize(file.size)}</div>
                        </div>
                    `
        })

        reader.readAsDataURL(file)
      }
    })
  }

  // Format file size
  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes"

    const k = 1024
    const sizes = ["Bytes", "KB", "MB", "GB"]
    const i = Math.floor(Math.log(bytes) / Math.log(k))

    return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
  }

  // Syntax highlighting for code blocks
  let hljs // Declare hljs
  const codeBlocks = document.querySelectorAll("pre code")

  if (codeBlocks.length > 0 && typeof hljs !== "undefined") {
    codeBlocks.forEach((block) => {
      hljs.highlightBlock(block)
    })
  }

  // Confirm delete actions
  const deleteButtons = document.querySelectorAll(".delete-confirm")

  deleteButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      if (!confirm("Are you sure you want to delete this? This action cannot be undone.")) {
        e.preventDefault()
      }
    })
  })

  // Mark notifications as read
  const notificationItems = document.querySelectorAll(".notification-item.unread")

  notificationItems.forEach((item) => {
    item.addEventListener("click", function () {
      const notificationId = this.dataset.id

      // Send AJAX request to mark as read
      if (notificationId) {
        const xhr = new XMLHttpRequest()
        xhr.open("POST", "mark_notification_read.php", true)
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded")
        xhr.send("id=" + notificationId)

        // Remove unread class
        this.classList.remove("unread")
      }
    })
  })
})
