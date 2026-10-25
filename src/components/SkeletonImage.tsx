import { useState, type ReactNode } from "react";

type SkeletonImageProps = {
  src: string;
  alt: string;
  className?: string;
  imageClassName?: string;
  loading?: "eager" | "lazy";
  children?: ReactNode;
};

export default function SkeletonImage({
  src,
  alt,
  className = "",
  imageClassName = "",
  loading = "lazy",
  children,
}: SkeletonImageProps) {
  const [loaded, setLoaded] = useState(false);

  return (
    <div className={`relative overflow-hidden bg-gray-100 ${className}`}>
      {!loaded && (
        <div className="absolute inset-0 animate-pulse bg-gradient-to-r from-gray-100 via-gray-200 to-gray-100" />
      )}
      <img
        src={src}
        alt={alt}
        className={`transition-opacity duration-300 ${loaded ? "opacity-100" : "opacity-0"} ${imageClassName}`}
        loading={loading}
        onLoad={() => setLoaded(true)}
      />
      {children}
    </div>
  );
}
