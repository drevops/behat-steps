import { useEffect, useRef } from 'react';
import SearchBar from '@theme-original/SearchBar';

// The search plugin's autocomplete widget follows the ARIA 1.1 combobox
// pattern. Its "See all results" link and its "No results" message sit
// directly inside the listbox, and it names its clear button "✕".
export const patchSearchMarkup = (root) => {
  root.querySelectorAll('[role="combobox"][aria-owns]:not([aria-controls])').forEach((input) => {
    input.setAttribute('aria-controls', input.getAttribute('aria-owns'));
  });

  root.querySelectorAll('[role="listbox"] a[href]:not([role])').forEach((link) => {
    link.setAttribute('role', 'option');
  });

  root.querySelectorAll('[role="listbox"] [class*="noResults_"]:not([role])').forEach((message) => {
    message.setAttribute('role', 'option');
    message.setAttribute('aria-disabled', 'true');
  });

  root.querySelectorAll('[class*="searchClearButton_"]:not([aria-label])').forEach((button) => {
    button.setAttribute('aria-label', 'Clear search');
  });
};

const SearchBarWrapper = (props) => {
  const ref = useRef(null);

  useEffect(() => {
    const root = ref.current;

    // The autocomplete widget redraws on every query, so the markup is
    // patched again after each change.
    const observer = new MutationObserver(() => patchSearchMarkup(root));

    patchSearchMarkup(root);
    observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-owns'] });

    return () => observer.disconnect();
  }, []);

  return (
    <div ref={ref} style={{ display: 'contents' }}>
      <SearchBar {...props} />
    </div>
  );
};

export default SearchBarWrapper;
