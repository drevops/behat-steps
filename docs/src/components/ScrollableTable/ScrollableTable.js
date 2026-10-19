import { useEffect, useRef, useState } from 'react';

// A table wider than the page scrolls sideways, and the keyboard can only
// scroll an element that takes focus.
const ScrollableTable = (props) => {
  const ref = useRef(null);
  const [scrollable, setScrollable] = useState(false);

  useEffect(() => {
    const table = ref.current;

    // A focused table keeps its tab stop until it loses focus, since the
    // browser drops the focus of an element that stops being focusable.
    const update = () => setScrollable(table.scrollWidth > table.clientWidth || document.activeElement === table);
    const observer = new ResizeObserver(update);

    update();
    observer.observe(table);
    table.addEventListener('blur', update);

    return () => {
      observer.disconnect();
      table.removeEventListener('blur', update);
    };
  }, []);

  return <table {...props} ref={ref} tabIndex={scrollable ? 0 : undefined} />;
};

export default ScrollableTable;
