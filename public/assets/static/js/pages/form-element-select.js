let choices = document.querySelectorAll("select.choices, .choices[data-type]")
let initChoice
for (let i = 0; i < choices.length; i++) {
  const element = choices[i]
  const isSelect = element.tagName === 'SELECT'

  if (!isSelect || element.closest('.modal')) {
    continue
  }

  if (element.classList.contains("multiple-remove")) {
    initChoice = new Choices(element, {
      delimiter: ",",
      editItems: true,
      maxItemCount: -1,
      removeItemButton: true,
    })
  } else {
    initChoice = new Choices(element, {
      position: 'bottom',
    })
  }

  element.choicesInstance = initChoice
}
