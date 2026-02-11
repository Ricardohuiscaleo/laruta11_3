// Código de tracking para agregar a /concurso/index.astro
// Agregar este script al final del <body>

<script>
// Tracking automático del concurso
(function() {
    // Obtener parámetro source de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const source = urlParams.get('source') || 'DIRECT';
    
    // Enviar tracking
    fetch('/api/track_concurso_visit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            source: source
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Concurso visit tracked:', data);
    })
    .catch(error => {
        console.error('Error tracking visit:', error);
    });
})();
</script>