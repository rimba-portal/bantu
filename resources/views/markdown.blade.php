<div x-data="markdownViewer()" x-init="init()" wire:ignore class="space-y-4">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css/github-markdown.min.css">

<style>
.markdown-body {
    background-color: transparent !important;
    color: inherit !important;
}
/* Forces all markdown table rows, headers, and cells to be transparent */
.markdown-body table,
.markdown-body table tr,
.markdown-body table th,
.markdown-body table td {
    background-color: transparent !important;
}

/* Optional: Subtle borders to keep table structures readable on custom backgrounds */
.markdown-body table th,
.markdown-body table td {
    border: 1px solid rgba(128, 128, 128, 0.2) !important;
}

/* Optional: Removes GitHub's default zebra striping background on even rows */
.markdown-body table tr:nth-child(2n) {
    background-color: transparent !important;
}
</style>

    <div class="markdown-body">
        {!! str($content)->markdown()->sanitizeHtml() !!}
    </div>


</div>
