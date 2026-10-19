// Stands in for the Docusaurus component, which needs the router context that
// a unit test does not have.
const Link = ({ to, href, children, ...props }) => {
  return (
    <a href={to ?? href} {...props}>
      {children}
    </a>
  );
};

export default Link;
