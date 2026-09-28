/**
 * Open links in diagrams in the browser window instead of SVG frame
 *
 * Diagrams are embedded as <object> elements, so each SVG is its own frame and a link
 * without a target would navigate only that frame. We add target="_top" to such links.
 *
 * The SVG document may not be loaded yet when this runs (slow responses, objects in hidden
 * containers that load only when shown) and objects may be added to the page later
 * (templates or plugins rebuilding the DOM). So every object gets a load listener, which
 * also covers reloads, and new objects are picked up by a MutationObserver.
 */
(function () {
    const SELECTOR = 'object.diagrams-svg';

    /** @type {WeakSet<HTMLObjectElement>} objects that already have a load listener */
    const attached = new WeakSet();

    /**
     * Sets _top target for links within the SVG document of the given object
     *
     * Covers SVG <a> elements as well as HTML <a> elements in <foreignObject> labels.
     * Links that already have a target (e.g. _blank) are left alone.
     *
     * @param {HTMLObjectElement} object
     */
    function manipulateLinkTarget(object) {
        let doc;
        try {
            doc = object.contentDocument;
        } catch (e) {
            return; // not accessible, e.g. cross-origin
        }
        if (!doc) return;

        doc.querySelectorAll('a:not([target])').forEach(link => {
            link.setAttribute('target', '_top');
        });
    }

    /**
     * Fix links of the given object now and whenever its SVG (re)loads
     *
     * @param {HTMLObjectElement} object
     */
    function attach(object) {
        if (attached.has(object)) return;
        attached.add(object);

        object.addEventListener('load', () => manipulateLinkTarget(object));
        // the SVG may already have finished loading before the listener was added
        manipulateLinkTarget(object);
    }

    /**
     * Attach to all diagram objects in or at the given node
     *
     * @param {Node} node
     */
    function attachAll(node) {
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.matches(SELECTOR)) attach(node);
        node.querySelectorAll(SELECTOR).forEach(attach);
    }

    const bodyObserver = new MutationObserver(mutationsList => {
        for (const mutation of mutationsList) {
            mutation.addedNodes.forEach(attachAll);
        }
    });

    jQuery(function () {
        attachAll(document.body);
        bodyObserver.observe(document.body, {childList: true, subtree: true});
    });
})();
