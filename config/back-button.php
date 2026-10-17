<?php
?>

<div class="mb-3">
    <button
        type="button"
        onclick="goBack()"
        class="btn btn-outline-secondary"
    >
        ← Previous Page
    </button>
</div>

<script>
function goBack() {
    if (document.referrer && document.referrer !== window.location.href) {
        window.history.back();
    } else {
        window.location.href = "../index.php";
    }
}
</script>