type ProductGridSkeletonProps = {
  count?: number;
};

export default function ProductGridSkeleton({
  count = 6,
}: ProductGridSkeletonProps) {
  return (
    <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="animate-pulse overflow-hidden rounded-3xl border border-slate-200 bg-white"
        >
          <div className="aspect-4/3 bg-slate-200" />
          <div className="space-y-3 p-5">
            <div className="h-4 w-24 rounded-full bg-slate-200" />
            <div className="h-6 w-3/4 rounded-full bg-slate-200" />
            <div className="h-4 w-2/3 rounded-full bg-slate-200" />
            <div className="h-10 rounded-full bg-slate-200" />
          </div>
        </div>
      ))}
    </div>
  );
}
