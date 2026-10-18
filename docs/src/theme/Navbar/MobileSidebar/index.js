import { useEffect } from 'react';
import MobileSidebar from '@theme-original/Navbar/MobileSidebar';
import { useNavbarMobileSidebar } from '@docusaurus/theme-common/internal';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';

// About 1 second at 60 frames per second, well past the sidebar transition.
const FOCUS_FRAMES = 60;

// Makes everything outside the sidebar inert, so it works as a modal dialog,
// and returns a function that undoes it. The backdrop stays clickable,
// because a click on it closes the sidebar.
const makeOutsideInert = (sidebar) => {
  const inert = [];

  for (let node = sidebar; node.parentElement && node !== document.body; node = node.parentElement) {
    Array.from(node.parentElement.children).forEach((sibling) => {
      if (sibling !== node && !sibling.matches('.navbar-sidebar__backdrop') && !sibling.hasAttribute('inert')) {
        sibling.setAttribute('inert', '');
        inert.push(sibling);
      }
    });
  }

  return () => inert.forEach((element) => element.removeAttribute('inert'));
};

const MobileSidebarWrapper = (props) => {
  const { shown, toggle } = useNavbarMobileSidebar();

  useEffect(() => {
    const sidebar = document.querySelector('.navbar-sidebar');

    if (!shown || !sidebar) {
      return undefined;
    }

    const restore = makeOutsideInert(sidebar);
    let frame = null;

    // The sidebar turns visible partway through its CSS transition, and a
    // hidden element can't take focus.
    const focusWhenVisible = (framesLeft) => {
      if (getComputedStyle(sidebar).visibility === 'visible') {
        sidebar.querySelector(FOCUSABLE)?.focus();
      } else if (framesLeft > 0) {
        frame = requestAnimationFrame(() => focusWhenVisible(framesLeft - 1));
      }
    };

    const closeOnEscape = (event) => {
      if (event.key === 'Escape') {
        toggle();
      }
    };

    focusWhenVisible(FOCUS_FRAMES);
    document.addEventListener('keydown', closeOnEscape);

    return () => {
      cancelAnimationFrame(frame);
      document.removeEventListener('keydown', closeOnEscape);
      restore();

      // Focus left in the hidden sidebar is lost, while a page change has
      // already moved it to the new content.
      if (sidebar.contains(document.activeElement) || document.activeElement === document.body) {
        document.querySelector('.navbar__toggle')?.focus({ preventScroll: true });
      }
    };
  }, [shown, toggle]);

  return <MobileSidebar {...props} />;
};

export default MobileSidebarWrapper;
