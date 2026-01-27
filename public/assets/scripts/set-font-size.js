function setFontSize(percent) {
  document.documentElement.style.fontSize = percent + '%';
}
console.log('Window width:', window.innerWidth);
// jika resolusi monitor kurang dari sama dengan  1440px atur font size 85%, kurang dari sama dengan 1600px atur font size 90%, lebih dari 1600px atur font size 100%
if (window.innerWidth <= 1440) {
    setFontSize(85);
    console.log('Window width less than or equal to 1440px');
    console.log('Font size set to 85%');
} else if (window.innerWidth <= 1600) {
    setFontSize(90);
    console.log('Window width less than or equal to 1600px');
    console.log('Font size set to 90%');
} else {
    setFontSize(100);
    console.log('Window width greater than 1600px');
    console.log('Font size set to 100%');
}
