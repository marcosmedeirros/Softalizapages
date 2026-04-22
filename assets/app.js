document.addEventListener("DOMContentLoaded", () => {
  const input = document.querySelector("[data-search]");
  const items = document.querySelectorAll("[data-site], [data-form]");
  const editToggles = document.querySelectorAll("[data-edit-toggle]");

  editToggles.forEach((button) => {
    button.addEventListener("click", () => {
      const card = button.closest(".list-item");
      if (!card) return;
      const form = card.querySelector(".edit-site");
      if (!form) return;
      form.classList.toggle("is-hidden");
    });
  });

  if (!input) return;

  input.addEventListener("input", () => {
    const q = input.value.trim().toLowerCase();
    items.forEach((item) => {
      const label = item.getAttribute("data-site") || item.getAttribute("data-form") || "";
      const name = label.toLowerCase();
      item.style.display = name.includes(q) ? "flex" : "none";
    });
  });
});
