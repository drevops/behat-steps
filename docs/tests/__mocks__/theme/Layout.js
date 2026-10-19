// Stands in for the Docusaurus theme component, which needs the site context
// that a unit test does not have.
const Layout = ({ title, description, children }) => {
  return (
    <div data-testid="layout" data-title={title} data-description={description}>
      {children}
    </div>
  );
};

export default Layout;
