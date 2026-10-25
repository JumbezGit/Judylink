import { Link } from "react-router";
import { MapPin, Clock, Users, Star } from "lucide-react";
import { motion } from "framer-motion";
import type { Tour } from "../data/tours";
import SkeletonImage from "./SkeletonImage";

interface TourCardProps {
  tour: Tour;
  index?: number;
}

export default function TourCard({ tour, index = 0 }: TourCardProps) {
  return (
    <motion.div
      initial={false}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, delay: Math.min(index, 3) * 0.05 }}
      className="bg-white rounded-2xl overflow-hidden border border-[#2FABE1]/45 shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col group"
    >
      <SkeletonImage
          src={tour.image}
          alt={tour.name}
          className="h-52 bg-[#2FABE1]/10"
          imageClassName="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        >
        <div className="absolute top-3 left-3">
          <span className="px-3 py-1 bg-[#2FABE1] text-white text-xs font-semibold rounded-full shadow">
            {tour.category}
          </span>
        </div>
        <div className="absolute top-3 right-3 flex items-center gap-1 px-2 py-1 bg-[#172033]/70 text-white rounded-full text-xs font-medium backdrop-blur-sm">
          <Star size={11} className="fill-[#FAA723] text-[#FAA723]" />
          {tour.rating}
          <span className="text-white/70">({tour.reviewCount})</span>
        </div>
      </SkeletonImage>

      <div className="p-5 flex flex-col flex-1">
        <h3 className="font-bold text-[#172033] text-base leading-snug mb-2 line-clamp-2" style={{ fontFamily: "Poppins, sans-serif" }}>
          {tour.name}
        </h3>

        <div className="flex items-center gap-1.5 text-[#2FABE1] text-xs font-medium mb-3">
          <MapPin size={12} />
          {tour.location}
        </div>

        <p className="text-[#172033]/60 text-sm leading-relaxed line-clamp-2 mb-4 flex-1">
          {tour.description}
        </p>

        <div className="flex items-center gap-4 text-xs text-[#172033]/50 mb-4 border-t border-gray-100 pt-3">
          <span className="flex items-center gap-1">
            <Clock size={12} />
            {tour.duration}
          </span>
          <span className="flex items-center gap-1">
            <Users size={12} />
            {tour.groupSize}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <div>
            <span className="text-xs text-[#172033]/40 font-medium">From</span>
            <div className="text-xl font-bold text-[#2FABE1]" style={{ fontFamily: "Poppins, sans-serif" }}>
              ${tour.price}
              <span className="text-xs text-[#172033]/40 font-normal ml-1">/ person</span>
            </div>
          </div>
          <Link
            to={`/tours/${tour.slug}`}
            className="px-4 py-2 bg-[#2FABE1] hover:bg-[#2FABE1] text-white text-sm font-semibold rounded-xl transition-colors duration-200"
          >
            View Details
          </Link>
        </div>
      </div>
    </motion.div>
  );
}



