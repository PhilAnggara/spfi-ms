let choices = document.querySelectorAll(".choices")
let initChoice
for (let i = 0; i < choices.length; i++) {
  if (choices[i].classList.contains("multiple-remove")) {
    initChoice = new Choices(choices[i], {
      delimiter: ",",
      editItems: true,
      maxItemCount: -1,
      removeItemButton: true,
    })
  } else {
    initChoice = new Choices(choices[i], {
      position: choices[i].closest('.product-form-modal') ? 'auto' : 'bottom',
    })
  }
  // Simpan instance agar bisa diakses saat set value dari modal edit
  choices[i].choicesInstance = initChoice
}
