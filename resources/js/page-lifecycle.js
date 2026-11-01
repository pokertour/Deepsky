export function onPageLoad(initialize) {
    let controller;
    let cleanup;

    const dispose = () => {
        controller?.abort();
        cleanup?.();
        controller = undefined;
        cleanup = undefined;
    };

    document.addEventListener('livewire:navigating', dispose);

    document.addEventListener('livewire:navigated', () => {
        dispose();
        controller = new AbortController();
        cleanup = initialize(controller.signal);
    });
}
