// Inicializa AOS
AOS.init({ once: true, offset: 50 });

// Função de Toast
function showToast(msg, isError = true) {
    const toast = document.getElementById('liveToast');
    if (!toast) return;
    
    document.getElementById('toastMsg').innerText = msg;
    const icon = document.getElementById('toastIcon');
    icon.className = isError ? 'ph-fill ph-warning-circle' : 'ph-fill ph-check-circle';
    icon.style.color = isError ? 'var(--red)' : '#10B981';
    
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 4000);
}

// Lógica AJAX do botão
async function confirmarAceite() {
    const checkbox = document.getElementById('termosCheck');
    if(!checkbox.checked) {
        showToast('Você precisa concordar com os termos para avançar.', true);
        return;
    }

    const btn = document.getElementById('btnConfirmar');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Selando...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'aceitar_proposta');
        
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();

        if(data.success) {
            var myModalEl = document.getElementById('modalAceite');
            var modal = bootstrap.Modal.getInstance(myModalEl);
            modal.hide();

            document.getElementById('stickyCta').style.display = 'none';

            const successBlock = document.getElementById('successBlock');
            successBlock.style.display = 'block';
            successBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            showToast('Acordo confirmado com sucesso!', false);
        } else {
            showToast(data.error || 'Erro ao confirmar', true);
            btn.innerHTML = 'Confirmar Início';
            btn.disabled = false;
        }
    } catch(e) {
        showToast('Erro de conexão. Tente novamente.', true);
        btn.innerHTML = 'Confirmar Início';
        btn.disabled = false;
    }
}

// Embed do YouTube com overlay (já tratado no HTML via API)
function loadYouTubeBackground() {
    // A função é chamada quando a API do YouTube carregar
    if(typeof YT !== 'undefined' && YT.Player) {
        new YT.Player('youtube-bg', {
            events: {
                onReady: function(event) {
                    event.target.mute();
                    event.target.playVideo();
                    event.target.setLoop(true);
                }
            }
        });
    }
}

// Carrega a API do YouTube assincronamente
function initYouTubeAPI() {
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    const firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    
    window.onYouTubeIframeAPIReady = loadYouTubeBackground;
}

// Inicializa
document.addEventListener('DOMContentLoaded', function() {
    if(document.getElementById('youtube-bg')) {
        initYouTubeAPI();
    }
});