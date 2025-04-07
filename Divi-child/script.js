function changeTab(selectedTab, tabId) {
  // Remove "active" class from all tabs
  document.querySelectorAll(".tab").forEach((tab) => {
    tab.classList.remove("active");
  });

  // Add "active" class to the clicked tab
  selectedTab.classList.add("active");

  // Hide all tab contents
  document.querySelectorAll(".tabContent").forEach((content) => {
    content.classList.remove("active");
  });

  // Show the selected tab content
  document.getElementById(tabId).classList.add("active");
}

const searchInput = document.getElementById("searchInput");
const backSpaceBox = document.getElementById("backSpaceBox");

// Show backspace icon when input is not empty
searchInput.addEventListener("input", () => {
  if (searchInput.value.trim() !== "") {
    backSpaceBox.style.display = "flex";
  } else {
    backSpaceBox.style.display = "none";
  }
});

// Clear the input field when backspace icon is clicked
backSpaceBox.addEventListener("click", () => {
  searchInput.value = "";
  backSpaceBox.style.display = "none"; // Hide backspace icon again
});

//slider
document.addEventListener("DOMContentLoaded", function () {
    const mainImage = document.getElementById("mainImage");
    const thumbnails = document.querySelectorAll(".thumbnail");
    const leftBtn = document.querySelector(".left-btn");
    const rightBtn = document.querySelector(".right-btn");

    if (!mainImage || thumbnails.length === 0) {
        console.warn("No images found for the slider.");
        return;
    }

    let currentIndex = 0;
    const images = Array.from(thumbnails).map((thumb) =>
        thumb.getAttribute("data-large")
    );

    thumbnails.forEach((thumbnail, index) => {
        thumbnail.addEventListener("click", function () {
            mainImage.src = this.getAttribute("data-large");
            currentIndex = index;
        });
    });

    leftBtn.addEventListener("click", function () {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        mainImage.src = images[currentIndex];
    });

    rightBtn.addEventListener("click", function () {
        currentIndex = (currentIndex + 1) % images.length;
        mainImage.src = images[currentIndex];
    });
});