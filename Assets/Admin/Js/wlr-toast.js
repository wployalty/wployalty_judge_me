if (typeof (wlr_toast_jquery) == 'undefined') {
    wlr_toast_jquery = jQuery.noConflict();
}
function createToast(text, type) {
    let newToast = document.createElement('div');
    newToast.classList.add('wlr-toast', type);
    newToast.innerHTML = `
        <div class="wlr-toast-content">
            <span class="wlr-toast-msg">${text}</span>
        </div>
    `;
    document.querySelector('.wlr-toast-notification').appendChild(newToast);
    newToast.timeOut = setTimeout(function() {
        newToast.style.animation = 'hide 0.3s ease 1 forwards';
        newToast.addEventListener('animationend', () => {
            newToast.remove();
        });
    }, 3000);
}