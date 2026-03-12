type PaginationControlsProps = {
  currentPage: number;
  lastPage: number;
  onPageChange: (page: number) => void;
};

export default function PaginationControls({
  currentPage,
  lastPage,
  onPageChange,
}: PaginationControlsProps) {
  if (lastPage <= 1) {
    return null;
  }

  const pages = Array.from({ length: lastPage }, (_, index) => index + 1);

  return (
    <div className="flex flex-wrap items-center gap-2">
      {pages.map((page) => (
        <button
          key={page}
          type="button"
          onClick={() => onPageChange(page)}
          className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
            page === currentPage
              ? "bg-slate-950 text-white"
              : "bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100"
          }`}
        >
          {page}
        </button>
      ))}

      <button
        type="button"
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage >= lastPage}
        className="rounded-full bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500"
      >
        Next
      </button>
    </div>
  );
}
