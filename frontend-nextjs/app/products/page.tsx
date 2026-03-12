import ProductsCatalog from "@/components/ProductsCatalog";

type ProductsPageProps = {
  searchParams: Promise<{
    page?: string;
    search?: string;
  }>;
};

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const params = await searchParams;
  const page = Number(params.page ?? "1");

  return (
    <ProductsCatalog
      initialPage={Number.isNaN(page) || page < 1 ? 1 : page}
      initialSearch={params.search ?? ""}
    />
  );
}
