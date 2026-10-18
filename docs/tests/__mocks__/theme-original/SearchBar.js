// Stands in for the search plugin's search bar with the markup its
// autocomplete widget draws once a query has results.
const SearchBar = () => {
  return (
    <div className="navbar__search">
      <input aria-label="Search" role="combobox" aria-owns="listbox-1" />
      <button type="button" className="searchClearButton_qk4g">✕</button>
      <span role="listbox" id="listbox-1">
        <div role="option" aria-selected="true">Result</div>
        <div className="hitFooter">
          <a href="/search?q=port">See all results</a>
        </div>
      </span>
    </div>
  );
};

export default SearchBar;
